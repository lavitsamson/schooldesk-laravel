<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Membership;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
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

    private static function student(School $school): Student
    {
        return Student::factory()->for($school)->create();
    }

    private static function guardian(School $school): Guardian
    {
        return Guardian::factory()->for($school)->create();
    }
}
