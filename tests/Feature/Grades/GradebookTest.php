<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpGradebook() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->assess = fn (array $attributes, array $marks): Assessment => tap(
        Assessment::factory()->for($this->class, 'schoolClass')->for($this->subject)->for($this->term)->create($attributes),
        function (Assessment $assessment) use ($marks): void {
            foreach ($marks as $index => $value) {
                Grade::factory()->for($assessment)->for($this->students[$index])->create(['marks' => $value]);
            }
        },
    );
});

it('lists the teacher\'s class-subjects with their assessments this term', function (): void {
    ($this->assess)(['held_on' => '2026-09-10'], []);
    ($this->assess)(['held_on' => '2026-10-01'], []);
    Assessment::factory()->for($this->class, 'schoolClass')->for($this->subject)->for($this->nextTerm)->create(['held_on' => '2027-02-01']);

    $science = Subject::factory()->for($this->school)->create(['name' => 'Science']);
    $grade8 = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A']);
    TeachingAssignment::query()->create(['school_id' => $this->school->id, 'class_id' => $grade8->id, 'subject_id' => $science->id, 'teacher_id' => $this->teacher->id]);
    // Someone else's subject in the teacher's class is not listed.
    TeachingAssignment::query()->create(['school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $science->id, 'teacher_id' => memberOf($this->school, Role::Teacher)->id]);

    actingInSchool($this->teacher, $this->school)
        ->get(route('grades.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades/Index')
            ->where('term.name', 'Autumn term') // today's term
            ->has('terms', 2)
            ->has('classes', 2)
            ->where('classes.0.name', 'Grade 7 A')
            ->has('classes.0.subjects', 1)
            ->where('classes.0.subjects.0.name', 'Mathematics')
            ->where('classes.0.subjects.0.assessments_count', 2)
            ->where('classes.0.can_view_results', false)
            ->where('classes.1.subjects.0.name', 'Science')
            ->where('canEditScale', false));

    actingInSchool($this->teacher, $this->school)
        ->get(route('grades.index', ['term' => $this->nextTerm->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('term.name', 'Spring term')
            ->where('classes.0.subjects.0.assessments_count', 1));
});

it('shows class teachers their class for its results, and admins everything', function (): void {
    $classTeacher = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $classTeacher->id]);

    actingInSchool($classTeacher, $this->school)
        ->get(route('grades.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('classes', 1)
            ->has('classes.0.subjects', 0)
            ->where('classes.0.is_class_teacher', true)
            ->where('classes.0.can_view_results', true));

    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->get(route('grades.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('classes.0.subjects.0.teacher', $this->teacher->name)
            ->where('classes.0.can_view_results', true)
            ->where('canEditScale', true));
});

it('shows the gradebook with each student\'s result so far', function (): void {
    $test = ($this->assess)(['name' => 'Test 1', 'max_marks' => 20, 'weight' => '1', 'held_on' => '2026-09-10'], ['18', '12', '10']);
    $exam = ($this->assess)(['name' => 'Exam', 'max_marks' => 100, 'weight' => '2', 'held_on' => '2026-10-01'], ['84', '61']);
    ($this->assess)(['name' => 'Test 2', 'held_on' => '2026-12-01'], []);

    actingInSchool($this->teacher, $this->school)
        ->get(route('grades.gradebook', [$this->class, $this->subject]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades/Gradebook')
            ->where('term.name', 'Autumn term')
            ->has('assessments', 3)
            ->where('assessments.0.name', 'Test 1')
            ->where('assessments.0.graded_count', 3)
            ->where('assessments.1.weight', '2.00')
            ->has('students', 3)
            ->where("students.0.marks.{$test->id}", '18.00')
            // (90 + 2 × 84) / 3 = 86 %
            ->where('students.0.percentage', '86.00')
            ->where('students.0.grade', 'B')
            // (60 + 2 × 61) / 3 = 60.67 %
            ->where('students.1.percentage', '60.67')
            ->where('students.1.grade', 'D')
            // Only the test is marked: 50 %
            ->where("students.2.marks.{$exam->id}", null)
            ->where('students.2.percentage', '50.00')
            ->where('average.percentage', '65.56')
            ->has('types', 3));
});
