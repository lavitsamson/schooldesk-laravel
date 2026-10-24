<?php

declare(strict_types=1);

use App\Actions\Grades\CalculateReportCard;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');
    foreach (setUpGradebook() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->english = Subject::factory()->for($this->school)->create(['name' => 'English', 'code' => 'ENG']);
    $this->art = Subject::factory()->for($this->school)->create(['name' => 'Art', 'code' => 'ART']);
    foreach ([$this->english, $this->art] as $subject) {
        TeachingAssignment::query()->create(['school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $subject->id, 'teacher_id' => $this->teacher->id]);
    }

    $this->assess = function (Subject $subject, array $attributes, array $marks, ?SchoolClass $class = null): Assessment {
        $assessment = Assessment::factory()->for($class ?? $this->class, 'schoolClass')->for($subject)->for($this->term)->create($attributes);
        foreach ($marks as $index => $value) {
            Grade::factory()->for($assessment)->for($this->students[$index])->create(['marks' => $value]);
        }

        return $assessment;
    };

    // Maths: Ada (90 + 2 × 84) / 3 = 86 %; English: Ada 70 %; Art: nothing marked yet.
    ($this->assess)($this->subject, ['name' => 'Test 1', 'max_marks' => 20, 'weight' => '1', 'held_on' => '2026-09-10'], ['18', '12']);
    ($this->assess)($this->subject, ['name' => 'Exam', 'max_marks' => 100, 'weight' => '2', 'held_on' => '2026-10-01'], ['84', '61']);
    ($this->assess)($this->english, ['name' => 'Essay', 'max_marks' => 10, 'held_on' => '2026-09-20'], ['7', null]);
    ($this->assess)($this->art, ['name' => 'Portfolio', 'max_marks' => 10, 'held_on' => '2026-12-10'], []);

    $this->classTeacher = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $this->classTeacher->id]);
});

it('calculates a student\'s term result as a snapshot', function (): void {
    foreach (['2026-10-05' => 'present', '2026-10-06' => 'late', '2026-10-07' => 'absent', '2026-10-08' => 'present'] as $date => $status) {
        AttendanceRecord::factory()->for($this->class, 'schoolClass')->for($this->students[0])->create(['date' => $date, 'status' => $status]);
    }
    // Outside the term: not counted.
    AttendanceRecord::factory()->for($this->class, 'schoolClass')->for($this->students[0])->create(['date' => '2026-07-30', 'status' => AttendanceStatus::Absent]);

    $report = inSchool($this->school, fn () => app(CalculateReportCard::class)->handle($this->students[0], $this->term));

    expect($report['student'])->toBe(['id' => $this->students[0]->id, 'first_name' => 'Ada', 'last_name' => 'Test', 'admission_no' => $this->students[0]->admission_no])
        ->and($report['class'])->toBe(['id' => $this->class->id, 'name' => 'Grade 7 A'])
        ->and($report['term']['name'])->toBe('Autumn term')
        ->and(array_column($report['subjects'], 'name'))->toBe(['Art', 'English', 'Mathematics'])
        ->and(array_column($report['subjects'], 'percentage'))->toBe([null, '70.00', '86.00'])
        ->and(array_column($report['subjects'], 'grade'))->toBe([null, 'C', 'B'])
        ->and(array_column($report['subjects'], 'graded'))->toBe([0, 1, 2])
        ->and(array_column($report['subjects'], 'assessments_count'))->toBe([1, 1, 2])
        ->and($report['subjects'][2]['assessments'][1])->toMatchArray(['name' => 'Exam', 'weight' => '2.00', 'max_marks' => 100, 'marks' => '84.00'])
        // (70 + 86) / 2: Art has no marks yet and is left out.
        ->and($report['overall'])->toBe(['percentage' => '78.00', 'grade' => 'C'])
        ->and($report['attendance'])->toBe(['days' => 4, 'present' => 2, 'absent' => 1, 'late' => 1, 'excused' => 0, 'rate' => 75.0])
        ->and($report['grade_scale'])->toHaveCount(6)
        ->and($report['grade_scale'][0])->toBe(['label' => 'A', 'min_percent' => '90.00', 'max_percent' => '100.00']);
});

it('counts marks from a class the student left during the term', function (): void {
    $other = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 7', 'section' => 'B']);
    ($this->assess)($this->subject, ['name' => 'Test in 7 B', 'max_marks' => 10, 'held_on' => '2026-09-01'], [], $other);
    $old = inSchool($this->school, fn () => Assessment::query()->where('name', 'Test in 7 B')->sole());
    Grade::factory()->for($old)->for($this->students[1])->create(['marks' => '3']);

    // Ben's maths: (60 + 2 × 61 + 30) / 4 = 53 %.
    $report = inSchool($this->school, fn () => app(CalculateReportCard::class)->handle($this->students[1], $this->term));
    $maths = collect($report['subjects'])->firstWhere('name', 'Mathematics');

    expect($maths['percentage'])->toBe('53.00')
        ->and($maths['assessments_count'])->toBe(3);
});

it('has no result without marks', function (): void {
    $report = inSchool($this->school, fn () => app(CalculateReportCard::class)->handle($this->students[0], $this->nextTerm));

    expect(array_column($report['subjects'], 'percentage'))->toBe([null, null, null])
        ->and($report['overall'])->toBe(['percentage' => null, 'grade' => null])
        ->and($report['attendance']['rate'])->toBeNull();
});

it('shows the class results by subject to the class teacher', function (): void {
    actingInSchool($this->classTeacher, $this->school)
        ->get(route('results.show', $this->class))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Results/Show')
            ->where('term.name', 'Autumn term')
            ->has('subjects', 3)
            ->where('subjects.2.name', 'Mathematics')
            ->has('students', 3)
            ->where('students.0.name', 'Ada Test')
            ->where("students.0.results.{$this->subject->id}.grade", 'B')
            ->where('students.0.overall.percentage', '78.00')
            // Ben: maths (60 + 2 × 61) / 3 = 60.67 %, no English marks.
            ->where('students.1.overall.percentage', '60.67')
            ->where("students.2.results.{$this->subject->id}.percentage", null));
});

it('shows what a student\'s report card will say', function (): void {
    actingInSchool($this->classTeacher, $this->school)
        ->get(route('results.student', [$this->class, $this->students[0]]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Results/Student')
            ->where('report.student.first_name', 'Ada')
            ->where('report.overall.grade', 'C')
            ->has('report.subjects', 3)
            ->has('terms', 2));
});

it('follows the grade scale', function (): void {
    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)->put(route('grade-scale.update'), ['bands' => [
        ['label' => 'Pass', 'min_percent' => '75', 'max_percent' => '100'],
        ['label' => 'Fail', 'min_percent' => '0', 'max_percent' => '74.99'],
    ]])->assertSessionHasNoErrors();

    actingInSchool($this->classTeacher, $this->school)
        ->get(route('results.show', $this->class))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.overall.grade', 'Pass')
            ->where('students.1.overall.grade', 'Fail'));
});

it('returns 404 for a student who is not in the class', function (): void {
    $elsewhere = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for(SchoolClass::factory()->for($this->school)->for($this->year)->create(), 'schoolClass')->for($elsewhere)->create();

    actingInSchool($this->classTeacher, $this->school)->get(route('results.student', [$this->class, $elsewhere]))->assertNotFound();
});

it('shows results only to admins and the class teacher', function (): void {
    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)->get(route('results.show', $this->class))->assertOk();

    // The maths teacher sees maths results in the gradebook, not the whole class's.
    actingInSchool($this->teacher, $this->school)->get(route('results.show', $this->class))->assertForbidden();
    actingInSchool($this->teacher, $this->school)->get(route('results.student', [$this->class, $this->students[0]]))->assertForbidden();
    actingInSchool(memberOf($this->school, Role::Parent), $this->school)->get(route('results.show', $this->class))->assertForbidden();
});
