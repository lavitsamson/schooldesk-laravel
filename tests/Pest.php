<?php

declare(strict_types=1);

use App\Actions\Api\IssueApiToken;
use App\Actions\Fees\GenerateTermInvoices;
use App\Actions\Members\GrantRole;
use App\Actions\ReportCards\SaveReportCardSnapshot;
use App\Actions\Setup\ApplyGradeScalePreset;
use App\Enums\GradeScalePreset;
use App\Enums\ImportRowOutcome;
use App\Enums\ReportCardStatus;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\AcademicYear;
use App\Models\AiCommentDraft;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Grade;
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
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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
 * setUpSchool() with fees: two terms (autumn starting 2026-08-17, spring 2027-01-04), a second
 * class (Grade 8 A), a tuition fee of 1,200.00 per term for every class, and three students:
 * Ada and Ben in Grade 7 A, Cleo in Grade 8 A. Ada's primary guardian has a parent account.
 *
 * @return array{school: School, year: AcademicYear, class: SchoolClass, otherClass: SchoolClass, term: Term, nextTerm: Term, tuition: FeeStructure, admin: User, parent: User, students: Collection<int, Student>}
 */
function setUpFees(): array
{
    [$school, $year, $class] = setUpSchool();
    $otherClass = SchoolClass::factory()->for($school)->for($year)->create(['name' => 'Grade 8', 'section' => 'A']);
    $term = Term::factory()->for($year)->create(['name' => 'Autumn term', 'starts_on' => '2026-08-17', 'ends_on' => '2026-12-18']);
    $nextTerm = Term::factory()->for($year)->create(['name' => 'Spring term', 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26']);
    $tuition = FeeStructure::factory()->for($year)->create(['name' => 'Tuition', 'amount' => 120_000, 'due_day_offset' => 14]);
    $admin = memberOf($school, Role::SchoolAdmin);
    $parent = memberOf($school, Role::Parent);

    $students = collect([['Ada', $class], ['Ben', $class], ['Cleo', $otherClass]])->map(function (array $row, int $i) use ($school): Student {
        [$name, $schoolClass] = $row;
        $student = Student::factory()->for($school)->create(['first_name' => $name, 'last_name' => 'Test']);
        Enrollment::factory()->for($schoolClass, 'schoolClass')->for($student)->create(['roll_no' => $i + 1, 'enrolled_on' => '2026-08-17']);

        return $student;
    });

    $students[0]->guardians()->attach(
        Guardian::factory()->for($school)->create(['user_id' => $parent->id, 'email' => $parent->email]),
        ['is_primary' => true],
    );

    return compact('school', 'year', 'class', 'otherClass', 'term', 'nextTerm', 'tuition', 'admin', 'parent', 'students');
}

/**
 * Generates a term's invoices as the daily run would, without queueing notifications.
 */
function generateInvoices(School $school, Term $term): int
{
    return inSchool($school, fn () => app(GenerateTermInvoices::class)->handle($term, notify: false));
}

/**
 * Posts a Stripe event to the webhook, signed with the configured secret as Stripe would.
 *
 * @param  array<string, mixed>  $event
 */
function stripeWebhook(array $event, ?string $secret = null, ?int $timestamp = null): TestResponse
{
    $payload = (string) json_encode($event);
    $timestamp ??= time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret ?? (string) config('services.stripe.webhook_secret'));

    /** @var TestCase $test */
    $test = test();

    return $test->call('POST', route('webhooks.stripe'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload);
}

/**
 * setUpReportCards() with a parent account: Paula Parent is the guardian of Ada and Ben
 * (siblings); Cleo belongs to another family.
 *
 * @return array{school: School, year: AcademicYear, class: SchoolClass, term: Term, nextTerm: Term, subject: Subject, teacher: User, students: Collection<int, Student>, classTeacher: User, assessment: Assessment, parent: User}
 */
function setUpFamily(): array
{
    $setup = setUpReportCards();
    $parent = memberOf($setup['school'], Role::Parent, ['name' => 'Paula Parent']);
    $guardian = Guardian::factory()->for($setup['school'])->create(['user_id' => $parent->id, 'email' => $parent->email]);

    $setup['students'][0]->guardians()->attach($guardian, ['is_primary' => true]);
    $setup['students'][1]->guardians()->attach($guardian, ['is_primary' => true]);

    return [...$setup, 'parent' => $parent];
}

/**
 * setUpFamily() with a school admin (Alice Admin) and a full record for Ada (admission number
 * S-1): a photo, an address, a mark with a remark, an absence with a note, a published report
 * card with a stored PDF and an AI comment draft, an invoice with a payment and its stored
 * receipt, a second guardian who is hers alone (Greta Gran), in-app notifications about her
 * and Ben, and an import row. Paula Parent stays the guardian of Ada and Ben. Fakes the local
 * disk.
 *
 * @return array{school: School, year: AcademicYear, class: SchoolClass, term: Term, nextTerm: Term, subject: Subject, teacher: User, students: Collection<int, Student>, classTeacher: User, assessment: Assessment, parent: User, admin: User, ada: Student, ben: Student, card: ReportCard, invoice: Invoice, payment: Payment, greta: Guardian}
 */
function setUpStudentRecords(): array
{
    Storage::fake('local');
    $setup = setUpFamily();
    $school = $setup['school'];
    [$ada, $ben] = [$setup['students'][0], $setup['students'][1]];
    $admin = memberOf($school, Role::SchoolAdmin, ['name' => 'Alice Admin']);

    $records = inSchool($school, function () use ($setup, $school, $ada): array {
        $photo = "schools/{$school->id}/students/{$ada->id}/photo.jpg";
        Storage::disk('local')->put($photo, 'jpeg');
        $ada->update(['admission_no' => 'S-1', 'address' => 'Hauptstr. 1', 'photo_path' => $photo]);

        Grade::query()->where('student_id', $ada->id)->sole()->update(['remarks' => 'Ada was unwell before the test.']);
        AttendanceRecord::factory()->absent()->for($setup['class'], 'schoolClass')->for($ada)
            ->create(['date' => '2026-10-05', 'note' => 'Flu']);

        $card = reportCardFor($school, $ada, $setup['term'], $setup['class']);
        $card->update([
            'status' => ReportCardStatus::Published, 'approved_at' => now(), 'published_at' => now(),
            'teacher_comment' => 'Ada has worked steadily.', 'pdf_path' => $card->pdfPath(),
        ]);
        Storage::disk('local')->put($card->pdfPath(), 'report card pdf');
        AiCommentDraft::query()->create([
            'school_id' => $school->id, 'report_card_id' => $card->id, 'prompt_hash' => str_repeat('a', 64),
            'draft_text' => 'Ada has worked steadily.', 'model' => 'fake', 'input_tokens' => 10, 'output_tokens' => 10, 'accepted' => true,
        ]);

        $invoice = Invoice::factory()->for($ada)->create(['term_id' => $setup['term']->id, 'number' => 'SD-2026-000001']);
        $payment = Payment::factory()->for($invoice)->create();
        $payment->forceFill(['receipt_path' => $payment->receiptPath()])->save();
        Storage::disk('local')->put($payment->receiptPath(), 'receipt pdf');

        Guardian::query()->where('user_id', $setup['parent']->id)->update(['name' => 'Paula Parent']);
        $greta = Guardian::factory()->for($school)->create(['name' => 'Greta Gran']);
        $ada->guardians()->attach($greta, ['is_primary' => false]);

        $batch = ImportBatch::factory()->for($school)->create();
        $batch->rows()->create(['row_number' => 2, 'key' => 'S-1', 'outcome' => ImportRowOutcome::Updated, 'values' => ['first_name' => 'Ada']]);

        return compact('card', 'invoice', 'payment', 'greta');
    });

    $notify = fn (array $data, string $type) => $setup['parent']->notifications()->create([
        'id' => (string) Str::uuid(), 'school_id' => $school->id, 'type' => $type, 'data' => $data,
    ]);
    $notify(['student_id' => $ada->id, 'first_name' => 'Ada', 'class' => 'Grade 7 A', 'date' => '2026-10-05'], 'student-absent');
    $notify(['invoice_id' => $records['invoice']->id, 'number' => 'SD-2026-000001', 'student_id' => $ada->id, 'first_name' => 'Ada', 'term' => 'Autumn term', 'total' => 120_000], 'invoice-issued');
    $notify(['payment_id' => $records['payment']->id, 'invoice_id' => $records['invoice']->id, 'number' => 'SD-2026-000001', 'amount' => 50_000], 'payment-received');
    $notify(['student_id' => $ben->id, 'first_name' => 'Ben', 'class' => 'Grade 7 A', 'date' => '2026-10-05'], 'student-absent');

    return [...$setup, ...$records, 'admin' => $admin, 'ada' => $ada->refresh(), 'ben' => $ben];
}

/**
 * A parent API token for the user at the school, issued as POST /api/v1/auth/token would
 * (factory users have the password "password").
 */
function apiToken(User $user, School $school): string
{
    return app(IssueApiToken::class)->handle($user->email, 'password', 'Test device', $school->id)->plainTextToken;
}

/**
 * The next request sends the bearer token. Sanctum's guard remembers the user of the previous
 * request in a test, so it is reset first.
 */
function withApiToken(string $token): TestCase
{
    app('auth')->forgetGuards();

    /** @var TestCase $test */
    $test = test();

    return $test->withToken($token)->withHeader('Accept', 'application/json');
}

/**
 * Generates (or recalculates) a student's report card as the class batch would.
 */
function reportCardFor(School $school, Student $student, Term $term, SchoolClass $class): ReportCard
{
    return inSchool($school, fn () => app(SaveReportCardSnapshot::class)->handle($student, $term, $class));
}
