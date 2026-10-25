<?php

declare(strict_types=1);

use App\Actions\Fees\SendOverdueReminders;
use App\Actions\Grades\BuildClassResults;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReportCardStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Invoice;
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
use Database\Seeders\DemoSchoolsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('seeds two set-up demo schools with working logins', function (): void {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2026-10-14 10:00:00'); // a Wednesday, two months into the demo year
    $this->seed(DemoSchoolsSeeder::class);

    expect(School::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Gymnasium am Main', 'Riverside International School']);

    $riverside = School::query()->where('slug', 'riverside')->sole();
    expect($riverside->isSetUp())->toBeTrue();

    app(CurrentSchool::class)->run($riverside, function (): void {
        $tom = User::query()->where('email', 'teacher@riverside.demo')->sole();
        $paula = Guardian::query()->where('email', 'parent@riverside.demo')->sole();

        expect(Subject::query()->count())->toBe(10)
            ->and($tom->hasRole(Role::Teacher))->toBeTrue()
            ->and(TeachingAssignment::query()->where('teacher_id', $tom->id)->count())->toBeGreaterThan(0)
            ->and(Student::query()->count())->toBe(384)
            // Every student has a class this year and exactly one primary guardian.
            ->and(Enrollment::query()->count())->toBe(384)
            ->and(GuardianStudent::query()->whereIn('student_id', Student::query()->select('id'))->where('is_primary', true)->count())->toBe(384)
            ->and($paula->user_id)->toBe(User::query()->where('email', 'parent@riverside.demo')->value('id'))
            ->and($paula->students()->pluck('first_name')->sort()->values()->all())->toBe(['Lotta', 'Pia']);

        // Attendance on every weekday up to yesterday, for every student; today is left open.
        $yesterday = AttendanceRecord::query()->where('date', '2026-10-13')->get();
        expect($yesterday)->toHaveCount(384)
            ->and(AttendanceRecord::query()->where('date', '2026-10-14')->exists())->toBeFalse()
            ->and(AttendanceRecord::query()->whereIn('date', ['2026-10-10', '2026-10-11'])->exists())->toBeFalse()
            ->and(AttendanceRecord::query()->min('date'))->toStartWith('2026-08-17')
            ->and($yesterday->whereNull('recorded_by'))->toBeEmpty()
            ->and(AttendanceRecord::query()->where('status', 'absent')->whereNull('guardian_notified_at')->exists())->toBeFalse();

        // Four assessments per class-subject this term; marks for those held before today.
        $assessments = Assessment::query()->get();
        $held = $assessments->filter(fn (Assessment $a): bool => $a->held_on->lt('2026-10-14'));
        expect($assessments)->toHaveCount(12 * 10 * 4)
            ->and($held->pluck('name')->unique()->sort()->values()->all())->toBe(['Project', 'Test 1'])
            ->and(Grade::query()->whereIn('assessment_id', $assessments->diff($held)->modelKeys())->exists())->toBeFalse()
            ->and(Grade::query()->count())->toBe($held->count() * 32)
            ->and(Grade::query()->whereNull('marks')->whereNull('remarks')->exists())->toBeFalse();

        $class = SchoolClass::query()->where('name', 'Grade 7')->where('section', 'A')->sole();
        $term = Term::query()->where('name', 'Autumn term')->sole();
        $results = app(BuildClassResults::class)->handle($class, $term);
        expect($results['subjects'])->toHaveCount(10)
            ->and(collect($results['students'])->pluck('overall.grade')->filter()->count())->toBe(32);

        // Grade 7 A's report cards at every stage; Pia's is published for the parent portal.
        $cards = ReportCard::query()->with('student')->get();
        expect($cards)->toHaveCount(32)
            ->and($cards->countBy(fn (ReportCard $card): string => $card->status->value)->sortKeys()->all())->toBe(['approved' => 2, 'draft' => 28, 'published' => 2])
            ->and($cards->whereNotNull('teacher_comment'))->toHaveCount(5)
            ->and($cards->first(fn (ReportCard $card): bool => $card->student->first_name === 'Pia')?->status)->toBe(ReportCardStatus::Published)
            ->and($cards->whereNotNull('pdf_path')->every(fn (ReportCard $card): bool => Storage::disk('local')->exists((string) $card->pdf_path)))->toBeTrue();

        // Autumn invoices for every student in mixed states; Paula's: Pia paid by card, Lotta partly.
        $invoices = Invoice::query()->with(['student', 'payments'])->get();
        $pia = $invoices->first(fn (Invoice $i): bool => $i->student->first_name === 'Pia' && $i->student->guardians()->where('email', 'parent@riverside.demo')->exists());
        $lotta = $invoices->first(fn (Invoice $i): bool => $i->student->first_name === 'Lotta');
        expect($invoices)->toHaveCount(384)
            ->and($invoices->map(fn (Invoice $i): string => $i->status->value)->unique()->sort()->values()->all())->toBe(['issued', 'paid', 'partially_paid', 'void'])
            ->and($invoices->every(fn (Invoice $i): bool => $i->due_on->toDateString() === '2026-08-31'))->toBeTrue()
            ->and($pia?->total)->toBe(163_000) // tuition 1,450 + activities 180
            ->and($pia?->status)->toBe(InvoiceStatus::Paid)
            ->and($pia?->payments->sole()->method)->toBe(PaymentMethod::Stripe)
            ->and($lotta?->status)->toBe(InvoiceStatus::PartiallyPaid)
            ->and(Payment::query()->where('paid_at', '>', now())->exists())->toBeFalse()
            // Overdue invoices count their reminders as sent: the scheduler has nothing to mail.
            ->and(app(SendOverdueReminders::class)->handle())->toBe(0);
    });

    Notification::assertNothingSent();

    // The demo parent's portal has notifications to show, stored without sending anything.
    $paula = User::query()->where('email', 'parent@riverside.demo')->sole();
    expect($paula->notifications()->where('school_id', $riverside->id)->count())->toBeGreaterThan(0)
        ->and($paula->unreadNotifications()->count())->toBe(3);

    expect($riverside->refresh()->aiCommentDraftsEnabled())->toBeTrue();

    $gymnasium = School::query()->where('slug', 'gymnasium-am-main')->sole();
    expect(app(CurrentSchool::class)->run($gymnasium, fn () => Student::query()->count()))->toBe(120);

    $this->post('/login', ['email' => 'admin@riverside.demo', 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get('/dashboard')->assertOk();
});
