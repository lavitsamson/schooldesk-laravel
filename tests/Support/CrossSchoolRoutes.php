<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\Membership;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Closure;

/**
 * The tenant isolation harness. Every route that takes a parameter must either have a case
 * here (a request for School B's record, which a School A admin must get a 404 for) or be
 * listed as exempt with a reason. TenantIsolationTest fails when a new route is in neither.
 */
final class CrossSchoolRoutes
{
    /**
     * Routes whose parameters do not identify a school's records.
     */
    public const EXEMPT = [
        'setup.show' => 'the parameter is a wizard step, not a record',
        'invitations.show' => 'access is proven by the signed link, not by membership',
        'invitations.accept' => 'access is proven by the signed link, not by membership',
        'password.reset' => 'Breeze password reset token',
        'verification.verify' => 'Breeze email verification (signed, own user only)',
        'storage.local' => 'Laravel local disk serving (signed URLs only)',
        'storage.local.upload' => 'Laravel local disk uploads (signed URLs only)',
    ];

    /**
     * Route name prefixes exempt as a whole.
     */
    public const EXEMPT_PREFIXES = [
        'horizon.' => 'the queue dashboard is platform-wide and for platform admins only',
    ];

    public static function isExempt(string $name): bool
    {
        return array_key_exists($name, self::EXEMPT)
            || array_filter(array_keys(self::EXEMPT_PREFIXES), fn (string $prefix): bool => str_starts_with($name, $prefix)) !== [];
    }

    /**
     * @return array<string, Closure(School): array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'schools.switch' => fn (School $other): array => [
                'post', route('schools.switch', $other),
            ],
            'members.resend' => fn (School $other): array => [
                'post', route('members.resend', Membership::factory()->for($other)->invited()->create()),
            ],
            'members.destroy' => fn (School $other): array => [
                'delete', route('members.destroy', Membership::factory()->for($other)->create()),
            ],

            'academic-years.update' => fn (School $other): array => [
                'put', route('academic-years.update', self::year($other)),
            ],
            'academic-years.make-current' => fn (School $other): array => [
                'post', route('academic-years.make-current', self::year($other, current: false)),
            ],
            'academic-years.destroy' => fn (School $other): array => [
                'delete', route('academic-years.destroy', self::year($other, current: false)),
            ],

            'classes.show' => fn (School $other): array => ['get', route('classes.show', self::schoolClass($other))],
            'classes.update' => fn (School $other): array => ['put', route('classes.update', self::schoolClass($other))],
            'classes.assignments' => fn (School $other): array => ['put', route('classes.assignments', self::schoolClass($other))],
            'classes.destroy' => fn (School $other): array => ['delete', route('classes.destroy', self::schoolClass($other))],

            'subjects.update' => fn (School $other): array => ['put', route('subjects.update', Subject::factory()->for($other)->create())],
            'subjects.destroy' => fn (School $other): array => ['delete', route('subjects.destroy', Subject::factory()->for($other)->create())],

            'students.show' => fn (School $other): array => ['get', route('students.show', self::student($other))],
            'students.edit' => fn (School $other): array => ['get', route('students.edit', self::student($other))],
            'students.update' => fn (School $other): array => ['put', route('students.update', self::student($other))],
            'students.destroy' => fn (School $other): array => ['delete', route('students.destroy', self::student($other))],
            'students.photo' => fn (School $other): array => ['get', route('students.photo', self::student($other))],

            'students.guardians.store' => fn (School $other): array => [
                'post', route('students.guardians.store', self::student($other)),
            ],
            'students.guardians.update' => fn (School $other): array => [
                'put', route('students.guardians.update', [self::student($other), self::guardian($other)]),
            ],
            'students.guardians.destroy' => fn (School $other): array => [
                'delete', route('students.guardians.destroy', [self::student($other), self::guardian($other)]),
            ],

            'guardians.update' => fn (School $other): array => ['put', route('guardians.update', self::guardian($other))],
            'guardians.destroy' => fn (School $other): array => ['delete', route('guardians.destroy', self::guardian($other))],
            'guardians.invite' => fn (School $other): array => ['post', route('guardians.invite', self::guardian($other))],

            'attendance.show' => fn (School $other): array => [
                'get', route('attendance.show', [self::schoolClass($other), '2026-10-01']),
            ],
            'attendance.update' => fn (School $other): array => [
                'put', route('attendance.update', [self::schoolClass($other), '2026-10-01']),
            ],
            'attendance.report' => fn (School $other): array => ['get', route('attendance.report', self::schoolClass($other))],
            'attendance.export' => fn (School $other): array => ['get', route('attendance.export', self::schoolClass($other))],

            'grades.gradebook' => fn (School $other): array => ['get', route('grades.gradebook', self::classSubject($other))],
            'assessments.store' => fn (School $other): array => ['post', route('assessments.store', self::classSubject($other))],
            'assessments.update' => fn (School $other): array => ['put', route('assessments.update', self::assessment($other))],
            'assessments.destroy' => fn (School $other): array => ['delete', route('assessments.destroy', self::assessment($other))],
            'assessments.grades' => fn (School $other): array => ['get', route('assessments.grades', self::assessment($other))],
            'assessments.grades.update' => fn (School $other): array => ['put', route('assessments.grades.update', self::assessment($other))],
            'results.show' => fn (School $other): array => ['get', route('results.show', self::schoolClass($other))],
            'results.student' => fn (School $other): array => [
                'get', route('results.student', [self::schoolClass($other), self::student($other)]),
            ],

            'imports.show' => fn (School $other): array => ['get', route('imports.show', ImportBatch::factory()->for($other)->create())],
            'imports.error-report' => fn (School $other): array => [
                'get', route('imports.error-report', ImportBatch::factory()->for($other)->withErrorReport()->create()),
            ],
        ];
    }

    private static function year(School $school, bool $current = true): AcademicYear
    {
        $factory = AcademicYear::factory()->for($school);

        return ($current ? $factory : $factory->next())->create();
    }

    private static function schoolClass(School $school): SchoolClass
    {
        return SchoolClass::factory()->for($school)->for(self::year($school))->create();
    }

    /**
     * A class and a subject taught in it.
     *
     * @return array{0: SchoolClass, 1: Subject}
     */
    private static function classSubject(School $school): array
    {
        $class = self::schoolClass($school);
        $subject = Subject::factory()->for($school)->create();
        TeachingAssignment::query()->create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => User::factory()->create()->id,
        ]);

        return [$class, $subject];
    }

    private static function assessment(School $school): Assessment
    {
        [$class, $subject] = self::classSubject($school);

        return Assessment::factory()->for($class, 'schoolClass')->for($subject)->create();
    }

    private static function student(School $school): Student
    {
        return Student::factory()->for($school)->create();
    }

    private static function guardian(School $school): Guardian
    {
        return Guardian::factory()->for($school)->create();
    }
}
