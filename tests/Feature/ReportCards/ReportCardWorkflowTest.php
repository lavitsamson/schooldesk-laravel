<?php

declare(strict_types=1);

use App\Enums\CommentSource;
use App\Enums\ReportCardStatus;
use App\Enums\Role;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\User;
use App\Notifications\ReportCardPublished;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpReportCards() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->card = reportCardFor($this->school, $this->students[0], $this->term, $this->class);
    $this->as = fn (?User $user = null) => actingInSchool($user ?? $this->classTeacher, $this->school);
});

it('shows the report card with its comment and links', function (): void {
    $ben = reportCardFor($this->school, $this->students[1], $this->term, $this->class);

    ($this->as)()->get(route('report-cards.show', $this->card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportCards/Show')
            ->where('card.status', 'draft')
            ->where('card.comment', null)
            ->where('report.student.first_name', 'Ada')
            ->where('report.overall.grade', 'A')
            ->where('schoolClass.name', 'Grade 7 A')
            ->where('siblings', ['previous' => null, 'next' => $ben->id])
            ->where('draft', null)
            ->where('ai.enabled', false)
            ->where('card.pdf_url', fn (string $url): bool => str_contains($url, 'signature=')));
});

it('saves the teacher\'s comment', function (): void {
    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => '  Ada worked hard.  '])
        ->assertSessionHasNoErrors();

    expect($this->card->refresh()->teacher_comment)->toBe('Ada worked hard.')
        ->and($this->card->comment_source)->toBe(CommentSource::Manual);

    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => str_repeat('a', 1201)])
        ->assertSessionHasErrors('comment');
    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => ''])
        ->assertSessionHasNoErrors();
    expect($this->card->refresh()->teacher_comment)->toBeNull()->and($this->card->comment_source)->toBeNull();
});

it('approves a report card: the snapshot is frozen and the PDF stored', function (): void {
    ($this->as)()->post(route('report-cards.approve', $this->card))->assertSessionHasErrors('comment');

    $this->card->update(['teacher_comment' => 'Ada worked hard.']);
    ($this->as)()->post(route('report-cards.approve', $this->card))->assertSessionHasNoErrors();

    $this->card->refresh();
    expect($this->card->status)->toBe(ReportCardStatus::Approved)
        ->and($this->card->approved_by)->toBe($this->classTeacher->id)
        ->and($this->card->approved_at)->not->toBeNull()
        ->and($this->card->pdf_path)->toBe("schools/{$this->school->id}/report-cards/{$this->term->id}/{$this->card->id}.pdf");
    Storage::disk('local')->assertExists($this->card->pdf_path);
    expect(Storage::disk('local')->get($this->card->pdf_path))->toStartWith('%PDF');

    // Marks corrected later do not change the approved card, nor does regenerating.
    inSchool($this->school, fn () => Grade::query()->update(['marks' => '2']));
    reportCardFor($this->school, $this->students[0], $this->term, $this->class);
    ($this->as)()->post(route('report-cards.refresh', $this->card))->assertSessionHasErrors('status');
    ($this->as)()->put(route('report-cards.comment', $this->card), ['comment' => 'Changed'])->assertSessionHasErrors('comment');

    expect($this->card->refresh()->data['overall']['percentage'])->toBe('90.00')
        ->and($this->card->teacher_comment)->toBe('Ada worked hard.');
});

it('recalculates a draft from the current marks', function (): void {
    inSchool($this->school, fn () => Grade::query()->where('student_id', $this->students[0]->id)->update(['marks' => '15']));

    ($this->as)()->post(route('report-cards.refresh', $this->card))->assertSessionHasNoErrors();

    expect($this->card->refresh()->data['overall']['percentage'])->toBe('75.00');
});

it('reopens an approved report card and deletes its PDF', function (): void {
    $this->card->update(['teacher_comment' => 'Ada worked hard.']);
    ($this->as)()->post(route('report-cards.approve', $this->card));
    $path = $this->card->refresh()->pdf_path;

    ($this->as)()->post(route('report-cards.reopen', $this->card))->assertSessionHasNoErrors();

    expect($this->card->refresh()->status)->toBe(ReportCardStatus::Draft)
        ->and($this->card->pdf_path)->toBeNull()
        ->and($this->card->approved_by)->toBeNull()
        ->and($this->card->teacher_comment)->toBe('Ada worked hard.');
    Storage::disk('local')->assertMissing((string) $path);

    ($this->as)()->post(route('report-cards.reopen', $this->card))->assertSessionHasErrors('status');
});

it('publishes an approved report card and notifies the guardians', function (): void {
    Notification::fake();
    $parent = User::factory()->create();
    $this->students[0]->guardians()->attach(Guardian::factory()->for($this->school)->create(['user_id' => $parent->id]), ['is_primary' => true]);
    $this->students[0]->guardians()->attach(Guardian::factory()->for($this->school)->create(['email' => 'grandma@example.com']), ['is_primary' => false]);

    ($this->as)()->post(route('report-cards.publish', $this->card))->assertSessionHasErrors('status');

    $this->card->update(['teacher_comment' => 'Ada worked hard.']);
    ($this->as)()->post(route('report-cards.approve', $this->card));
    ($this->as)()->post(route('report-cards.publish', $this->card))->assertSessionHasNoErrors();

    expect($this->card->refresh()->status)->toBe(ReportCardStatus::Published)
        ->and($this->card->published_at)->not->toBeNull();

    Notification::assertSentTo($parent, ReportCardPublished::class, function (ReportCardPublished $notification, array $channels) use ($parent): bool {
        $mail = $notification->toMail($parent);

        return $channels === ['mail', 'database']
            && $mail->subject === "Ada's report card for the Autumn term"
            && $notification->toArray($parent)['first_name'] === 'Ada';
    });
    Notification::assertSentTo(new AnonymousNotifiable, ReportCardPublished::class, fn ($n, array $channels, AnonymousNotifiable $notifiable): bool => array_key_exists('grandma@example.com', $notifiable->routes['mail']));

    // Published is final.
    ($this->as)()->post(route('report-cards.reopen', $this->card))->assertSessionHasErrors('status');
    ($this->as)()->post(route('report-cards.publish', $this->card))->assertSessionHasErrors('status');
});

it('publishes all approved report cards of a class, and skips drafts', function (): void {
    Notification::fake();
    $ben = reportCardFor($this->school, $this->students[1], $this->term, $this->class);
    foreach ([$this->card, $ben] as $card) {
        $card->update(['teacher_comment' => 'Good term.']);
    }
    ($this->as)()->post(route('report-cards.approve', $this->card));

    ($this->as)()->post(route('report-cards.publish-class', $this->class), ['term' => $this->term->id])
        ->assertSessionHas('status', '1 report card published.');

    expect($this->card->refresh()->status)->toBe(ReportCardStatus::Published)
        ->and($ben->refresh()->status)->toBe(ReportCardStatus::Draft);
});

it('serves the PDF through signed links only', function (): void {
    $signed = fn (ReportCard $card): string => URL::temporarySignedRoute('report-cards.pdf', now()->addMinutes(30), ['reportCard' => $card]);

    // A draft is a preview, rendered on the fly and not stored.
    ($this->as)()->get($signed($this->card))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    Storage::disk('local')->assertDirectoryEmpty('/');

    $this->card->update(['teacher_comment' => 'Ada worked hard.']);
    ($this->as)()->post(route('report-cards.approve', $this->card));
    ($this->as)()->get($signed($this->card))
        ->assertOk()
        ->assertDownload('report-card-ada-test-autumn-term.pdf');

    ($this->as)()->get(route('report-cards.pdf', $this->card))->assertForbidden();
    actingInSchool($this->teacher, $this->school)->get($signed($this->card))->assertForbidden();
});

it('keeps report cards from subject teachers and parents', function (): void {
    foreach ([$this->teacher, memberOf($this->school, Role::Parent)] as $user) {
        ($this->as)($user)->get(route('report-cards.show', $this->card))->assertForbidden();
        ($this->as)($user)->put(route('report-cards.comment', $this->card), ['comment' => 'x'])->assertForbidden();
        ($this->as)($user)->post(route('report-cards.approve', $this->card))->assertForbidden();
    }

    ($this->as)(memberOf($this->school, Role::SchoolAdmin))->get(route('report-cards.show', $this->card))->assertOk();
});
