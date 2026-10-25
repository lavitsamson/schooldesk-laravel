<?php

declare(strict_types=1);

use App\Enums\ReportCardStatus;
use App\Enums\Role;
use App\Jobs\GenerateReportCard;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\ReportCardBatch;
use App\Models\SchoolClass;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpReportCards() as $key => $value) {
        $this->{$key} = $value;
    }
});

it('queues one job per student in a batch on the reports queue', function (): void {
    Bus::fake();

    actingInSchool($this->classTeacher, $this->school)
        ->post(route('report-cards.generate', $this->class), ['term' => $this->term->id])
        ->assertRedirect(route('report-cards.class', ['schoolClass' => $this->class, 'term' => $this->term->id]))
        ->assertSessionHas('status', 'Generating 3 report cards.');

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 3
        && $batch->queue() === 'reports'
        && $batch->allowsFailures()
        && $batch->jobs->every(fn (GenerateReportCard $job): bool => $job->schoolId === $this->school->id && $job->termId === $this->term->id));

    $record = inSchool($this->school, fn () => ReportCardBatch::query()->sole());
    expect($record->total)->toBe(3)->and($record->job_batch_id)->not->toBeNull();
});

it('generates a draft report card with the term result for everyone in the class', function (): void {
    actingInSchool($this->classTeacher, $this->school)
        ->post(route('report-cards.generate', $this->class), ['term' => $this->term->id])
        ->assertSessionHasNoErrors();

    $cards = inSchool($this->school, fn () => ReportCard::query()->orderBy('student_id')->get());

    expect($cards)->toHaveCount(3)
        ->and($cards->pluck('status')->unique()->all())->toBe([ReportCardStatus::Draft])
        ->and($cards[0]->data['student']['first_name'])->toBe('Ada')
        // A JSON column may reorder keys (MySQL does), hence toEqual.
        ->and($cards[0]->data['overall'])->toEqual(['percentage' => '90.00', 'grade' => 'A'])
        ->and($cards[0]->class_id)->toBe($this->class->id);

    actingInSchool($this->classTeacher, $this->school)
        ->get(route('report-cards.class', ['schoolClass' => $this->class, 'term' => $this->term->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportCards/Class')
            ->has('students', 3)
            ->where('students.0.name', 'Ada Test')
            ->where('students.0.card.status', 'draft')
            ->where('students.0.card.overall_grade', 'A')
            ->where('students.0.card.has_comment', false)
            ->where('batch.total', 3)
            ->where('batch.is_finished', true)
            ->where('batch.progress', 100)
            ->where('batch.failed', 0));
});

it('recalculates drafts and keeps their comment, but leaves approved report cards alone', function (): void {
    $ada = reportCardFor($this->school, $this->students[0], $this->term, $this->class);
    $ben = reportCardFor($this->school, $this->students[1], $this->term, $this->class);
    $ada->update(['teacher_comment' => 'Well done.']);
    $ben->update(['status' => ReportCardStatus::Approved, 'teacher_comment' => 'Good.']);

    // Marks change after the first generation.
    inSchool($this->school, fn () => Grade::query()->update(['marks' => '10']));

    actingInSchool($this->classTeacher, $this->school)
        ->post(route('report-cards.generate', $this->class), ['term' => $this->term->id])
        ->assertSessionHasNoErrors();

    expect($ada->refresh()->data['overall']['percentage'])->toBe('50.00')
        ->and($ada->teacher_comment)->toBe('Well done.')
        ->and($ben->refresh()->data['overall']['percentage'])->toBe('60.00')
        ->and($ben->status)->toBe(ReportCardStatus::Approved);
});

it('does not start a second run while one is still going', function (): void {
    Bus::fake();

    actingInSchool($this->classTeacher, $this->school)->post(route('report-cards.generate', $this->class), ['term' => $this->term->id]);
    actingInSchool($this->classTeacher, $this->school)
        ->post(route('report-cards.generate', $this->class), ['term' => $this->term->id])
        ->assertSessionHasErrors(['generate' => 'Report cards for this class are already being generated.']);

    Bus::assertBatchCount(1);
});

it('shows a running batch with its progress', function (): void {
    Bus::fake();
    actingInSchool($this->classTeacher, $this->school)->post(route('report-cards.generate', $this->class), ['term' => $this->term->id]);

    actingInSchool($this->classTeacher, $this->school)
        ->get(route('report-cards.class', ['schoolClass' => $this->class, 'term' => $this->term->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('batch.is_finished', false)
            ->where('batch.processed', 0)
            ->where('batch.progress', 0)
            ->where('students.0.card', null));
});

it('needs students in the class', function (): void {
    $empty = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A', 'class_teacher_id' => $this->classTeacher->id]);

    actingInSchool($this->classTeacher, $this->school)
        ->post(route('report-cards.generate', $empty), ['term' => $this->term->id])
        ->assertSessionHasErrors('generate');
});

it('lists the classes a user writes report cards for, with their progress', function (): void {
    SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 9', 'section' => 'B']);
    reportCardFor($this->school, $this->students[0], $this->term, $this->class)->update(['status' => ReportCardStatus::Published]);
    reportCardFor($this->school, $this->students[1], $this->term, $this->class);

    actingInSchool($this->classTeacher, $this->school)
        ->get(route('report-cards.index', ['term' => $this->term->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReportCards/Index')
            ->has('classes', 1)
            ->where('classes.0.name', 'Grade 7 A')
            ->where('classes.0.students', 3)
            ->where('classes.0.published', 1)
            ->where('classes.0.draft', 1)
            ->where('classes.0.approved', 0)
            ->where('aiDrafts', ['enabled' => false, 'canChange' => false]));

    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->get(route('report-cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('classes', 2)
            ->where('aiDrafts.canChange', true));
});

it('leaves report cards to admins and the class teacher', function (): void {
    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->get(route('report-cards.class', $this->class))
        ->assertOk();

    // The maths teacher enters marks but does not write this class's report cards.
    actingInSchool($this->teacher, $this->school)->get(route('report-cards.class', $this->class))->assertForbidden();
    actingInSchool($this->teacher, $this->school)->post(route('report-cards.generate', $this->class))->assertForbidden();
    actingInSchool(memberOf($this->school, Role::Parent), $this->school)->get(route('report-cards.index'))->assertForbidden();
});
