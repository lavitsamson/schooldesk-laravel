<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpGradebook() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->assessment = Assessment::factory()->for($this->class, 'schoolClass')->for($this->subject)->for($this->term)
        ->create(['name' => 'Fractions test', 'max_marks' => 20, 'held_on' => '2026-10-12']);

    $this->save = fn (array $grades, $user = null) => actingInSchool($user ?? $this->teacher, $this->school)
        ->putJson(route('assessments.grades.update', $this->assessment), ['grades' => $grades]);
    $this->row = fn (int $index, mixed $marks, ?string $remarks = null): array => [
        'student_id' => $this->students[$index]->id,
        'marks' => $marks,
        'remarks' => $remarks,
    ];
    $this->grades = fn (): Collection => inSchool($this->school, fn () => Grade::query()->orderBy('student_id')->get());
});

it('shows the grid with the students in the class on the day', function (): void {
    $joinedLater = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for($this->class, 'schoolClass')->for($joinedLater)->create(['enrolled_on' => '2026-10-13']);

    actingInSchool($this->teacher, $this->school)
        ->get(route('assessments.grades', $this->assessment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades/Sheet')
            ->where('assessment.name', 'Fractions test')
            ->where('assessment.max_marks', 20)
            ->where('schoolClass.name', 'Grade 7 A')
            ->where('subject.name', 'Mathematics')
            ->has('students', 3)
            ->where('students.0.name', 'Ada Test')
            ->where('students.0.marks', null)
            ->where('saved.count', 0));
});

it('autosaves the rows sent and leaves the others alone', function (): void {
    ($this->save)([($this->row)(0, '17.5'), ($this->row)(1, 12)])
        ->assertOk()
        ->assertJson(['saved' => 2, 'graded' => 2]);

    ($this->save)([($this->row)(1, '13', 'Careless mistakes')])->assertOk()->assertJson(['saved' => 1, 'graded' => 2]);

    $grades = ($this->grades)();
    expect($grades->pluck('marks')->all())->toBe(['17.50', '13.00'])
        ->and($grades->pluck('remarks')->all())->toBe([null, 'Careless mistakes'])
        ->and($grades->pluck('entered_by')->unique()->all())->toBe([$this->teacher->id]);

    actingInSchool($this->teacher, $this->school)
        ->get(route('assessments.grades', $this->assessment))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.marks', '17.50')
            ->where('students.1.remarks', 'Careless mistakes')
            ->where('saved.count', 2)
            ->where('saved.by', $this->teacher->name));
});

it('reads a decimal comma and treats blank cells as empty', function (): void {
    ($this->save)([($this->row)(0, ' 12,5 '), ($this->row)(1, '', '  Absent  ')])->assertOk();

    $grades = ($this->grades)();
    expect($grades->pluck('marks')->all())->toBe(['12.50', null])
        ->and($grades->pluck('remarks')->all())->toBe([null, 'Absent'])
        ->and(json_decode((string) ($this->save)([($this->row)(1, '')])->getContent(), true)['graded'])->toBe(1);
});

it('removes a row once both marks and remark are cleared', function (): void {
    ($this->save)([($this->row)(0, '15')]);
    ($this->save)([($this->row)(0, '')])->assertOk()->assertJson(['graded' => 0]);

    expect(($this->grades)())->toHaveCount(0);
});

it('validates marks against the assessment', function (mixed $marks, string $message): void {
    ($this->save)([($this->row)(0, '10'), ($this->row)(1, $marks)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['grades.1.marks' => $message]);

    // All or nothing: the valid row is not saved either.
    expect(($this->grades)())->toHaveCount(0);
})->with([
    'above max marks' => ['20.5', 'At most 20 marks.'],
    'negative' => ['-1', 'Marks cannot be negative.'],
    'not a number' => ['abc', 'Enter a number.'],
    'three decimals' => ['10.125', 'At most two decimals.'],
]);

it('rejects students who are not on the sheet', function (): void {
    $left = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for($this->class, 'schoolClass')->for($left)->create(['left_on' => '2026-10-01']);
    $otherSchool = Student::factory()->create();

    ($this->save)([['student_id' => $left->id, 'marks' => '10', 'remarks' => null]])->assertJsonValidationErrors('grades');
    ($this->save)([['student_id' => $otherSchool->id, 'marks' => '10', 'remarks' => null]])->assertJsonValidationErrors('grades');
    ($this->save)([($this->row)(0, '10'), ($this->row)(0, '11')])->assertJsonValidationErrors('grades.1.student_id');

    expect(($this->grades)())->toHaveCount(0);
});

it('keeps marks of students who moved class since', function (): void {
    ($this->save)([($this->row)(2, '16')]);
    $other = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 7', 'section' => 'B']);
    inSchool($this->school, fn () => Enrollment::query()->where('student_id', $this->students[2]->id)->update(['class_id' => $other->id]));

    actingInSchool($this->teacher, $this->school)
        ->get(route('assessments.grades', $this->assessment))
        ->assertInertia(fn (Assert $page) => $page->has('students', 3)->where('students.2.marks', '16.00'));

    ($this->save)([($this->row)(2, '17')])->assertOk();
});

it('lets only the subject teacher and admins enter marks', function (): void {
    $other = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $other->id]);

    actingInSchool($other, $this->school)->get(route('assessments.grades', $this->assessment))->assertForbidden();
    ($this->save)([($this->row)(0, '10')], $other)->assertForbidden();
    ($this->save)([($this->row)(0, '10')], memberOf($this->school, Role::Parent))->assertForbidden();
    ($this->save)([($this->row)(0, '10')], memberOf($this->school, Role::SchoolAdmin))->assertOk();
});
