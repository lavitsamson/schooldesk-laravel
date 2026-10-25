<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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

            'report-cards.class' => fn (School $other): array => ['get', route('report-cards.class', self::schoolClass($other))],
            'report-cards.generate' => fn (School $other): array => ['post', route('report-cards.generate', self::schoolClass($other))],
            'report-cards.publish-class' => fn (School $other): array => ['post', route('report-cards.publish-class', self::schoolClass($other))],
            'report-cards.show' => fn (School $other): array => ['get', route('report-cards.show', self::reportCard($other))],
            'report-cards.refresh' => fn (School $other): array => ['post', route('report-cards.refresh', self::reportCard($other))],
            'report-cards.approve' => fn (School $other): array => ['post', route('report-cards.approve', self::reportCard($other))],
            'report-cards.reopen' => fn (School $other): array => ['post', route('report-cards.reopen', ReportCard::factory()->for(self::schoolClass($other), 'schoolClass')->approved()->create())],
            'report-cards.publish' => fn (School $other): array => ['post', route('report-cards.publish', ReportCard::factory()->for(self::schoolClass($other), 'schoolClass')->approved()->create())],
            'report-cards.comment' => fn (School $other): array => ['put', route('report-cards.comment', self::reportCard($other))],
            'report-cards.ai-draft' => fn (School $other): array => ['post', route('report-cards.ai-draft', self::reportCard($other))],
            // Even with a valid signature, another school's report card is not found.
            'report-cards.pdf' => fn (School $other): array => [
                'get', URL::temporarySignedRoute('report-cards.pdf', now()->addMinutes(5), ['reportCard' => self::reportCard($other)]),
            ],

            'fee-structures.update' => fn (School $other): array => ['put', route('fee-structures.update', FeeStructure::factory()->for(self::year($other))->create())],
            'fee-structures.destroy' => fn (School $other): array => ['delete', route('fee-structures.destroy', FeeStructure::factory()->for(self::year($other))->create())],
            'invoices.show' => fn (School $other): array => ['get', route('invoices.show', self::invoice($other))],
            'invoices.void' => fn (School $other): array => ['post', route('invoices.void', self::invoice($other))],
            'invoices.checkout' => fn (School $other): array => ['post', route('invoices.checkout', self::invoice($other))],
            'invoices.payments.store' => fn (School $other): array => ['post', route('invoices.payments.store', self::invoice($other))],
            'invoices.pdf' => fn (School $other): array => [
                'get', URL::temporarySignedRoute('invoices.pdf', now()->addMinutes(5), ['invoice' => self::invoice($other)]),
            ],
            'payments.receipt' => fn (School $other): array => [
                'get', URL::temporarySignedRoute('payments.receipt', now()->addMinutes(5), ['payment' => Payment::factory()->for(self::invoice($other))->create()]),
            ],

            'imports.show' => fn (School $other): array => ['get', route('imports.show', ImportBatch::factory()->for($other)->create())],
            'imports.error-report' => fn (School $other): array => [
                'get', route('imports.error-report', ImportBatch::factory()->for($other)->withErrorReport()->create()),
            ],

            'family.attendance' => fn (School $other): array => ['get', route('family.attendance', self::student($other))],
            'family.results' => fn (School $other): array => ['get', route('family.results', self::student($other))],
            'family.report-cards' => fn (School $other): array => ['get', route('family.report-cards', self::student($other))],
            'notifications.read' => fn (School $other): array => ['post', route('notifications.read', self::notification($other))],
        ];
    }

    /**
     * The parent API: a parent of School A, with a token for School A, asks for School B's
     * records (ApiTenantIsolationTest). Same rule: 404, never 403.
     *
     * @return array<string, Closure(School): array{0: string, 1: string}>
     */
    public static function apiCases(): array
    {
        return [
            'api.v1.children.attendance' => fn (School $other): array => ['get', route('api.v1.children.attendance', self::student($other))],
            'api.v1.children.grades' => fn (School $other): array => ['get', route('api.v1.children.grades', self::student($other))],
            'api.v1.children.report-cards' => fn (School $other): array => ['get', route('api.v1.children.report-cards', self::student($other))],
            'api.v1.report-cards.pdf' => fn (School $other): array => [
                'get', route('api.v1.report-cards.pdf', ReportCard::factory()->for(self::schoolClass($other), 'schoolClass')->published()->create()),
            ],
            'api.v1.invoices.checkout-session' => fn (School $other): array => ['post', route('api.v1.invoices.checkout-session', self::invoice($other))],
            'api.v1.notifications.read' => fn (School $other): array => ['post', route('api.v1.notifications.read', self::notification($other))],
        ];
    }

    /**
     * A notification of one of the school's parents, sent in that school.
     */
    private static function notification(School $school): string
    {
        $parent = User::factory()->create();

        return $parent->notifications()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'type' => 'student-absent',
            'data' => ['student_id' => 1, 'first_name' => 'Ada', 'class' => 'Grade 7 A', 'date' => '2026-10-02'],
        ])->id;
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

    private static function reportCard(School $school): ReportCard
    {
        return ReportCard::factory()->for(self::schoolClass($school), 'schoolClass')->create();
    }

    private static function invoice(School $school): Invoice
    {
        return Invoice::factory()->for(self::student($school))->create();
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
