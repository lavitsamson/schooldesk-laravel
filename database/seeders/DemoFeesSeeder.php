<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Fees\GenerateTermInvoices;
use App\Enums\FeeFrequency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fee structures for the current year and the first term's invoices in mixed states, so the
 * fees dashboard has something to show: most paid (by bank transfer, card or cash), some partly
 * paid, some overdue and one void. Invoices are generated through GenerateTermInvoices, then
 * dated back as if issued two weeks before the term started.
 *
 * The parent demo login's children: one invoice paid by card, the other partly paid, so the
 * parent sees both a receipt and an open balance. Seeding sends no mail: invoices are
 * generated without notifications, payments are bulk-inserted (receipts are rendered on first
 * download), and overdue invoices count their reminders as sent, so the scheduler does not
 * mail every demo family the next morning.
 *
 * @phpstan-type Fee array{name: string, amount: int, frequency: FeeFrequency, classes?: list<string>}
 */
class DemoFeesSeeder extends Seeder
{
    private const DUE_DAYS = 14;

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly GenerateTermInvoices $generate,
    ) {}

    /**
     * @param  list<Fee>  $fees
     */
    public function seedSchool(School $school, array $fees, User $office, ?string $familyEmail = null): void
    {
        $this->currentSchool->run($school, function (School $school) use ($fees, $office, $familyEmail): void {
            $year = AcademicYear::query()->current()->with(['terms', 'classes'])->firstOrFail();
            /** @var Term $term */
            $term = $year->terms->sortBy('starts_on')->first();

            $this->createFees($year, $fees);
            $this->generate->handle($term, notify: false);

            $issuedAt = CarbonImmutable::parse($term->starts_on->subDays(14)->toDateString().' 08:00', $school->timezone)->utc();
            $dueOn = $term->starts_on->addDays(self::DUE_DAYS);
            Invoice::query()->where('term_id', $term->id)->update(['issued_at' => $issuedAt, 'due_on' => $dueOn->toDateString()]);

            $this->payInvoices($school, $term, $dueOn, $office, $this->familyStudentIds($familyEmail));
        });
    }

    /**
     * @param  list<Fee>  $fees
     */
    private function createFees(AcademicYear $year, array $fees): void
    {
        foreach ($fees as $fee) {
            $classes = isset($fee['classes'])
                ? $year->classes->filter(fn (SchoolClass $class): bool => in_array($class->displayName(), $fee['classes'], true))->pluck('id')->all()
                : [null];

            foreach ($classes as $classId) {
                FeeStructure::query()->create([
                    'academic_year_id' => $year->id,
                    'class_id' => $classId,
                    'name' => $fee['name'],
                    'amount' => $fee['amount'],
                    'frequency' => $fee['frequency'],
                    'due_day_offset' => self::DUE_DAYS,
                ]);
            }
        }
    }

    /**
     * @param  array<int, string>  $family  student id => "paid" or "partial"
     */
    private function payInvoices(School $school, Term $term, CarbonImmutable $dueOn, User $office, array $family): void
    {
        $today = $school->today();
        $invoices = Invoice::query()->where('term_id', $term->id)->orderBy('number')->get(['id', 'student_id', 'number', 'total']);
        $methods = [PaymentMethod::BankTransfer, PaymentMethod::Stripe, PaymentMethod::Cash];
        $payments = [];
        $statuses = [];

        foreach ($invoices as $i => $invoice) {
            $state = $family[$invoice->student_id] ?? match (true) {
                $i % 50 === 49 => 'void',
                $i % 10 <= 5 => 'paid',
                $i % 10 <= 7 => 'partial',
                default => 'open',
            };

            // Paid between a week before and two weeks after the due date, never in the future.
            $paidOn = $dueOn->addDays(($i * 7) % 21 - 7)->min($today->subDay());
            $method = match (true) {
                $state === 'partial' => PaymentMethod::Cash,
                isset($family[$invoice->student_id]) => PaymentMethod::Stripe,
                default => $methods[$i % 3],
            };
            $amount = match ($state) {
                'paid' => $invoice->total,
                'partial' => intdiv($invoice->total, 200) * 100,
                default => 0,
            };

            if ($amount > 0) {
                $paidAt = CarbonImmutable::parse($paidOn->toDateString().' 10:30', $school->timezone)->utc();
                // Families quote the invoice number; the bank adds its own transaction id.
                $bankReference = $method === PaymentMethod::BankTransfer
                    ? sprintf('%s TRX%08d', $invoice->number, crc32("{$school->slug}-{$invoice->id}") % 100_000_000)
                    : null;
                $payments[] = [
                    'school_id' => $school->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'refunded_amount' => 0,
                    'method' => $method->value,
                    'stripe_payment_intent_id' => $method === PaymentMethod::Stripe ? "pi_demo_{$school->id}_{$invoice->id}" : null,
                    'reference' => $bankReference,
                    'bank_reference' => $bankReference === null ? null : Payment::normalizeBankReference($bankReference),
                    'paid_at' => $paidAt,
                    'recorded_by' => $method === PaymentMethod::Stripe ? null : $office->id,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ];
            }

            $status = match ($state) {
                'void' => InvoiceStatus::Void,
                default => InvoiceStatus::forAmounts($invoice->total, $amount),
            };
            $statuses[$status->value][] = $invoice->id;
        }

        foreach (array_chunk($payments, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }

        foreach ($statuses as $status => $ids) {
            Invoice::query()->whereIn('id', $ids)->update([
                'status' => $status,
                'voided_at' => $status === InvoiceStatus::Void->value ? $dueOn->subDays(10) : null,
            ]);
        }

        // Reminders an overdue invoice would have had by now (none are sent while seeding).
        $overdue = fn () => Invoice::query()->where('term_id', $term->id)->overdueOn($today);
        $overdue()->where('due_on', '<=', $today->subDays(10)->toDateString())->update(['reminders_sent' => 2]);
        $overdue()->whereBetween('due_on', [$today->subDays(9)->toDateString(), $today->subDays(3)->toDateString()])->update(['reminders_sent' => 1]);
    }

    /**
     * The demo parent's children: the older one's invoice paid, the younger one's partly paid.
     *
     * @return array<int, string>
     */
    private function familyStudentIds(?string $email): array
    {
        if ($email === null) {
            return [];
        }

        $children = Guardian::query()->where('email', $email)->firstOrFail()
            ->students()->orderBy('date_of_birth')->pluck('students.id')->values();

        return $children->mapWithKeys(fn (mixed $id, int $i): array => [(int) $id => $i === 0 ? 'paid' : 'partial'])->all();
    }
}
