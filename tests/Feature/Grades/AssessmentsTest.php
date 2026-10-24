<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpGradebook() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->input = fn (array $overrides = []): array => [
        'term_id' => $this->term->id,
        'name' => 'Fractions test',
        'type' => 'test',
        'held_on' => '2026-10-12',
        'max_marks' => 20,
        'weight' => '1',
        ...$overrides,
    ];
    $this->store = fn (array $overrides = [], $user = null) => actingInSchool($user ?? $this->teacher, $this->school)
        ->post(route('assessments.store', [$this->class, $this->subject]), ($this->input)($overrides));
    $this->assessment = fn (array $attributes = []): Assessment => Assessment::factory()
        ->for($this->class, 'schoolClass')->for($this->subject)->for($this->term)->create($attributes);
});

it('lets the subject teacher add an assessment and goes on to its marks', function (): void {
    $response = ($this->store)(['weight' => '1,5']);

    $assessment = inSchool($this->school, fn () => Assessment::query()->sole());
    $response->assertRedirect(route('assessments.grades', $assessment))
        ->assertSessionHas('status', 'Fractions test added. Enter the marks below.');

    expect($assessment->type)->toBe(AssessmentType::Test)
        ->and($assessment->weight)->toBe('1.50')
        ->and($assessment->max_marks)->toBe(20)
        ->and($assessment->term_id)->toBe($this->term->id)
        ->and($assessment->held_on->toDateString())->toBe('2026-10-12')
        ->and($assessment->created_by)->toBe($this->teacher->id);
});

it('validates the assessment', function (array $overrides, string $error): void {
    ($this->store)($overrides)->assertSessionHasErrors($error);

    expect(inSchool($this->school, fn () => Assessment::query()->count()))->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'unknown type' => [['type' => 'quiz'], 'type'],
    'no marks' => [['max_marks' => 0], 'max_marks'],
    'too many marks' => [['max_marks' => 1001], 'max_marks'],
    'zero weight' => [['weight' => '0'], 'weight'],
    'three decimals' => [['weight' => '1.255'], 'weight'],
    'not a date' => [['held_on' => '12.10.2026'], 'held_on'],
    'outside the term' => [['held_on' => '2027-01-10'], 'held_on'],
]);

it('only takes terms of the class\'s year', function (): void {
    $otherYear = AcademicYear::factory()->for($this->school)->next()->create();
    $foreign = Term::factory()->for($otherYear)->create();

    ($this->store)(['term_id' => $foreign->id])->assertNotFound();
});

it('updates an assessment, but not below marks already entered', function (): void {
    $assessment = ($this->assessment)();
    Grade::factory()->for($assessment)->for($this->students[0])->create(['marks' => '18.50']);

    actingInSchool($this->teacher, $this->school)
        ->put(route('assessments.update', $assessment), [...($this->input)(['max_marks' => 18]), 'term_id' => null])
        ->assertSessionHasErrors(['max_marks' => 'A student already has 18.5 marks. Change those marks first.']);

    actingInSchool($this->teacher, $this->school)
        ->put(route('assessments.update', $assessment), [
            'name' => 'Fractions', 'type' => 'exam', 'held_on' => '2026-10-13', 'max_marks' => 25, 'weight' => '2',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Fractions saved.');

    expect($assessment->refresh()->only(['name', 'max_marks', 'weight']))->toBe(['name' => 'Fractions', 'max_marks' => 25, 'weight' => '2.00'])
        ->and($assessment->type)->toBe(AssessmentType::Exam);
});

it('never moves an assessment to another term', function (): void {
    $assessment = ($this->assessment)();

    actingInSchool($this->teacher, $this->school)
        ->put(route('assessments.update', $assessment), ($this->input)(['term_id' => $this->nextTerm->id]))
        ->assertSessionHasErrors('term_id');
});

it('deletes an assessment with its marks', function (): void {
    $assessment = ($this->assessment)();
    Grade::factory()->for($assessment)->for($this->students[0])->create();

    actingInSchool($this->teacher, $this->school)
        ->delete(route('assessments.destroy', $assessment))
        ->assertRedirect(route('grades.gradebook', [$this->class, $this->subject, 'term' => $this->term->id]));

    expect(inSchool($this->school, fn () => [Assessment::query()->count(), Grade::query()->count()]))->toBe([0, 0]);
});

it('lets school admins manage every class-subject', function (): void {
    $admin = memberOf($this->school, Role::SchoolAdmin);

    ($this->store)([], $admin)->assertSessionHasNoErrors();
    actingInSchool($admin, $this->school)->delete(route('assessments.destroy', ($this->assessment)()))->assertRedirect();
});

it('forbids teachers to set assessments in subjects they do not teach', function (): void {
    $other = memberOf($this->school, Role::Teacher);
    // Even the class teacher: the maths teacher sets the maths tests.
    $this->class->update(['class_teacher_id' => $other->id]);
    $assessment = ($this->assessment)();

    ($this->store)([], $other)->assertForbidden();
    actingInSchool($other, $this->school)->put(route('assessments.update', $assessment), ($this->input)())->assertForbidden();
    actingInSchool($other, $this->school)->delete(route('assessments.destroy', $assessment))->assertForbidden();
    actingInSchool($other, $this->school)->get(route('grades.gradebook', [$this->class, $this->subject]))->assertForbidden();

    $parent = memberOf($this->school, Role::Parent);
    ($this->store)([], $parent)->assertForbidden();
    actingInSchool($parent, $this->school)->get(route('grades.index'))->assertForbidden();
});

it('has no gradebook for a subject not taught in the class', function (): void {
    $art = Subject::factory()->for($this->school)->create();
    $admin = memberOf($this->school, Role::SchoolAdmin);

    actingInSchool($admin, $this->school)->get(route('grades.gradebook', [$this->class, $art]))->assertNotFound();
    actingInSchool($admin, $this->school)
        ->post(route('assessments.store', [$this->class, $art]), ($this->input)())
        ->assertNotFound();
});

it('keeps the gradebook when the subject is no longer taught in the class', function (): void {
    ($this->assessment)();
    inSchool($this->school, fn () => TeachingAssignment::query()->delete());

    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->get(route('grades.gradebook', [$this->class, $this->subject]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('assessments', 1));
});

it('keeps classes, subjects, terms and years with assessments as history', function (): void {
    ($this->assessment)();
    $admin = memberOf($this->school, Role::SchoolAdmin);
    inSchool($this->school, fn () => Enrollment::query()->delete());

    actingInSchool($admin, $this->school)->delete(route('classes.destroy', $this->class))
        ->assertSessionHasErrors(['class' => 'Assessments have been set in this class, so it is kept as history.']);
    actingInSchool($admin, $this->school)->delete(route('subjects.destroy', $this->subject))
        ->assertSessionHasErrors(['subject' => 'Marks have been entered for this subject, so it is kept as history.']);

    actingInSchool($admin, $this->school)->put(route('academic-years.update', $this->year), [
        'name' => '2026/27',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'terms' => [['id' => $this->nextTerm->id, 'name' => 'Spring term', 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26']],
    ])->assertSessionHasErrors(['terms' => 'Assessments have been set in Autumn term, so it cannot be removed.']);

    expect(inSchool($this->school, fn () => Term::query()->whereKey($this->term->id)->exists()))->toBeTrue();
});
