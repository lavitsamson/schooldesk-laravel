<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Actions\ReportCards\SaveReportCardSnapshot;
use App\Actions\Setup\ApplyGradeScalePreset;
use App\Enums\GradeScalePreset;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Membership;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * An active member of the school with the given role.
 *
 * @param  array<string, mixed>  $attributes
 */
function memberOf(School $school, Role $role = Role::SchoolAdmin, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Membership::factory()->for($school)->for($user)->create();
    app(GrantRole::class)->handle($user, $school, $role);

    return $user;
}

/**
 * Act as the user, with the school selected in the session as the school switcher would.
 */
function actingInSchool(User $user, School $school): TestCase
{
    /** @var TestCase $test */
    $test = test();

    return $test->actingAs($user)->withSession([SetCurrentSchool::SESSION_KEY => $school->id]);
}

/**
 * Runs the callback with the school as the current school, e.g. to query school-owned
 * models in assertions (they fail closed without a school context).
 *
 * @template T
 *
 * @param  callable(School): T  $callback
 * @return T
 */
function inSchool(School $school, callable $callback): mixed
{
    return app(CurrentSchool::class)->run($school, $callback);
}

/**
 * A set-up school with a current academic year (2026/27) and one class, Grade 7 A.
 *
 * @return array{0: School, 1: AcademicYear, 2: SchoolClass}
 */
function setUpSchool(): array
{
    $school = School::factory()->completedSetup()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->for($year)->create(['name' => 'Grade 7', 'section' => 'A']);

    return [$school, $year, $class];
}

/**
 * setUpSchool() with the A–F grade scale, two terms, Mathematics taught in Grade 7 A by a
 * teacher, and three students enrolled there (Ada, Ben, Cleo Test, roll numbers 1–3).
 *
 * @return array{school: School, year: AcademicYear, class: SchoolClass, term: Term, nextTerm: Term, subject: Subject, teacher: User, students: Collection<int, Student>}
 */
function setUpGradebook(): array
{
    [$school, $year, $class] = setUpSchool();

    inSchool($school, fn () => app(ApplyGradeScalePreset::class)->handle(GradeScalePreset::LetterAToF));

    $term = Term::factory()->for($year)->create(['name' => 'Autumn term', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18']);
    $nextTerm = Term::factory()->for($year)->create(['name' => 'Spring term', 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26']);
    $subject = Subject::factory()->for($school)->create(['name' => 'Mathematics', 'code' => 'MATH']);
    $teacher = memberOf($school, Role::Teacher);

    TeachingAssignment::query()->create([
        'school_id' => $school->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
    ]);

    $students = collect(['Ada', 'Ben', 'Cleo'])->map(function (string $name, int $i) use ($school, $class): Student {
        $student = Student::factory()->for($school)->create(['first_name' => $name, 'last_name' => 'Test']);
        Enrollment::factory()->for($class, 'schoolClass')->for($student)->create(['roll_no' => $i + 1]);

        return $student;
    });

    return compact('school', 'year', 'class', 'term', 'nextTerm', 'subject', 'teacher', 'students');
}

/**
 * setUpGradebook() with a class teacher for Grade 7 A and marks in the autumn term: one maths
 * test (Ada 18/20 = 90 %, Ben 12/20 = 60 %, Cleo not marked).
 *
 * @return array{school: School, year: AcademicYear, class: SchoolClass, term: Term, nextTerm: Term, subject: Subject, teacher: User, students: Collection<int, Student>, classTeacher: User, assessment: Assessment}
 */
function setUpReportCards(): array
{
    $setup = setUpGradebook();
    $classTeacher = memberOf($setup['school'], Role::Teacher);
    $setup['class']->update(['class_teacher_id' => $classTeacher->id]);

    $assessment = Assessment::factory()->for($setup['class'], 'schoolClass')->for($setup['subject'])->for($setup['term'])
        ->create(['name' => 'Test 1', 'max_marks' => 20, 'held_on' => '2026-09-10']);
    Grade::factory()->for($assessment)->for($setup['students'][0])->create(['marks' => '18']);
    Grade::factory()->for($assessment)->for($setup['students'][1])->create(['marks' => '12']);

    return [...$setup, 'classTeacher' => $classTeacher, 'assessment' => $assessment];
}

/**
 * Generates (or recalculates) a student's report card as the class batch would.
 */
function reportCardFor(School $school, Student $student, Term $term, SchoolClass $class): ReportCard
{
    return inSchool($school, fn () => app(SaveReportCardSnapshot::class)->handle($student, $term, $class));
}
