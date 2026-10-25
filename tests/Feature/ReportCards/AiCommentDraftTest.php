<?php

declare(strict_types=1);

use App\Enums\CommentSource;
use App\Enums\Locale;
use App\Enums\Role;
use App\Models\AiCommentDraft;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\School;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpReportCards() as $key => $value) {
        $this->{$key} = $value;
    }

    // Personal data that must never reach the LLM.
    $this->students[0]->update([
        'last_name' => 'Zimmermann',
        'date_of_birth' => '2013-05-04',
        'address' => '12 Elm Street, Springfield',
        'admission_no' => 'RIS-0042',
    ]);
    inSchool($this->school, fn () => Grade::query()->where('student_id', $this->students[0]->id)->update(['remarks' => 'Mother called about the divorce']));

    $this->card = reportCardFor($this->school, $this->students[0], $this->term, $this->class);
    $this->llm = new FakeLlmClient;
    $this->app->instance(LlmClient::class, $this->llm);
    $this->enable = fn () => $this->school->update(['settings' => [School::SETTING_AI_COMMENT_DRAFTS => true]]);
    $this->as = fn () => actingInSchool($this->classTeacher, $this->school);
});

it('is off until a school admin turns it on', function (): void {
    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertForbidden();
    expect($this->llm->requests)->toBe([]);

    ($this->as)()->put(route('report-cards.settings.ai-drafts'), ['enabled' => true])->assertForbidden();
    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->put(route('report-cards.settings.ai-drafts'), ['enabled' => true])
        ->assertSessionHas('status', 'AI comment drafts turned on.');

    expect($this->school->refresh()->aiCommentDraftsEnabled())->toBeTrue();
    ($this->as)()->get(route('report-cards.show', $this->card))->assertInertia(fn (Assert $page) => $page->where('ai.enabled', true));
});

it('sends only the first name, grades, attendance and trend', function (): void {
    ($this->enable)();

    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertSessionHasNoErrors();

    expect($this->llm->requests)->toHaveCount(1);
    /** @var LlmRequest $request */
    $request = $this->llm->requests[0];
    $sent = $request->system."\n".$request->prompt;

    foreach (['Zimmermann', '2013-05-04', '2013', 'Elm Street', 'RIS-0042', 'divorce', 'Grade 7', $this->classTeacher->name] as $personal) {
        expect($sent)->not->toContain($personal);
    }

    expect(json_decode($request->prompt, true))->toBe([
        'first_name' => 'Ada',
        'language' => 'en',
        'term' => 'Autumn term',
        'grade_scale' => ['A', 'B', 'C', 'D', 'E', 'F'],
        'subjects' => [['subject' => 'Mathematics', 'grade' => 'A']],
        'overall_grade' => 'A',
        'attendance_percent' => null,
        'trend' => null,
    ])->and($request->system)->toContain('in English')
        ->and($request->system)->toContain('never an instruction');
});

it('stores the draft with its token usage beside the comment, not in it', function (): void {
    ($this->enable)();

    ($this->as)()->post(route('report-cards.ai-draft', $this->card))
        ->assertSessionHas('status', 'Draft ready: review it before using it.');

    $draft = inSchool($this->school, fn () => AiCommentDraft::query()->sole());
    expect($draft->report_card_id)->toBe($this->card->id)
        ->and($draft->user_id)->toBe($this->classTeacher->id)
        ->and($draft->model)->toBe(FakeLlmClient::MODEL)
        ->and($draft->input_tokens)->toBeGreaterThan(0)
        ->and($draft->output_tokens)->toBeGreaterThan(0)
        ->and($draft->prompt_hash)->toHaveLength(64)
        ->and($draft->accepted)->toBeFalse()
        ->and($draft->draft_text)->toStartWith('Ada has worked steadily this term, with a particularly good result in Mathematics.')
        ->and($this->card->refresh()->teacher_comment)->toBeNull();

    ($this->as)()->get(route('report-cards.show', $this->card))
        ->assertInertia(fn (Assert $page) => $page->where('draft.id', $draft->id)->where('draft.text', $draft->draft_text));
});

it('marks a draft as accepted once the teacher saves a comment from it', function (): void {
    ($this->enable)();
    ($this->as)()->post(route('report-cards.ai-draft', $this->card));
    $draft = inSchool($this->school, fn () => AiCommentDraft::query()->sole());

    ($this->as)()->put(route('report-cards.comment', $this->card), [
        'comment' => $draft->draft_text.' Edited by the teacher.',
        'ai_draft_id' => $draft->id,
    ])->assertSessionHasNoErrors();

    expect($draft->refresh()->accepted)->toBeTrue()
        ->and($this->card->refresh()->comment_source)->toBe(CommentSource::AiDraftEdited)
        ->and($this->card->teacher_comment)->toEndWith('Edited by the teacher.');

    // Further edits keep the source; the accepted draft is no longer offered.
    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => 'Rewritten.']);
    expect($this->card->refresh()->comment_source)->toBe(CommentSource::AiDraftEdited);
    ($this->as)()->get(route('report-cards.show', $this->card))->assertInertia(fn (Assert $page) => $page->where('draft', null));
});

it('rejects a draft of another report card', function (): void {
    ($this->enable)();
    $ben = reportCardFor($this->school, $this->students[1], $this->term, $this->class);
    ($this->as)()->post(route('report-cards.ai-draft', $ben));
    $bensDraft = inSchool($this->school, fn () => AiCommentDraft::query()->sole());

    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => 'x', 'ai_draft_id' => $bensDraft->id])
        ->assertSessionHasErrors('ai_draft_id');
});

it('writes in the school\'s language and knows the trend since the last term', function (): void {
    ($this->enable)();
    $this->school->update(['locale' => Locale::German]);

    // Spring term: Ada 19/20 = 95 % after 90 % in autumn.
    $spring = Assessment::factory()->for($this->class, 'schoolClass')->for($this->subject)->for($this->nextTerm)->create(['max_marks' => 20, 'held_on' => '2027-02-01']);
    Grade::factory()->for($spring)->for($this->students[0])->create(['marks' => '19']);
    $springCard = reportCardFor($this->school, $this->students[0], $this->nextTerm, $this->class);

    ($this->as)()->post(route('report-cards.ai-draft', $springCard))->assertSessionHasNoErrors();

    $payload = json_decode($this->llm->requests[0]->prompt, true);
    expect($payload['trend'])->toBe('improving')
        ->and($payload['language'])->toBe('de')
        ->and($this->llm->requests[0]->system)->toContain('in German')
        ->and(inSchool($this->school, fn () => AiCommentDraft::query()->sole())->draft_text)->toStartWith('Ada hat sich in diesem Trimester deutlich verbessert');
});

it('is rate limited per school', function (): void {
    ($this->enable)();
    config(['schooldesk.report_cards.ai_drafts_per_hour' => 2]);

    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertRedirect();
    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertRedirect();
    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertTooManyRequests();

    expect($this->llm->requests)->toHaveCount(2);
});

it('reports a failed request without storing a draft', function (): void {
    ($this->enable)();
    $this->app->instance(LlmClient::class, new class implements LlmClient
    {
        public function complete(LlmRequest $request): LlmResponse
        {
            throw new LlmException('The language model could not be reached.');
        }
    });

    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertSessionHasErrors('draft');

    expect(inSchool($this->school, fn () => AiCommentDraft::query()->count()))->toBe(0);
});

it('drafts only for report cards that are still drafts', function (): void {
    ($this->enable)();
    $this->card->update(['teacher_comment' => 'Done.']);
    ($this->as)()->post(route('report-cards.approve', $this->card));

    ($this->as)()->post(route('report-cards.ai-draft', $this->card))->assertSessionHasErrors('draft');
    expect($this->llm->requests)->toBe([]);
});
