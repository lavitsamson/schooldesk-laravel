<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\FeeFrequency;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Jobs\SendInvoiceIssuedNotification;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Term;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues the term's invoices: one per student enrolled during the term, with an item per fee
 * structure that applies to the student's class (ADR 0008):
 * - "term" fees on every term's invoice;
 * - "annual" fees once per year: left out when an earlier invoice of the year has them;
 * - "one_off" fees once, for students in their first year at the school.
 *
 * Idempotent: students who already have an invoice for the term (even a void one) are
 * skipped, and the unique (student, term) index backs that up. A lock per school and term
 * keeps the daily run and an admin's "Generate now" from running at the same time.
 */
final class GenerateTermInvoices
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly NextInvoiceNumber $nextNumber,
    ) {}

    /**
     * @return int the number of invoices created
     */
    public function handle(Term $term, bool $notify = true): int
    {
        $school = $this->currentSchool->get();
        $lock = Cache::lock("fees:generate:{$school->id}:{$term->id}", 600);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['term' => __('The invoices for this term are being generated already.')]);
        }

        try {
            return $this->generate($term, $notify);
        } finally {
            $lock->release();
        }
    }

    private function generate(Term $term, bool $notify): int
    {
        $school = $this->currentSchool->get();
        $term->loadMissing('academicYear');
        $year = $term->academicYear;

        $structures = FeeStructure::query()->where('academic_year_id', $year->id)->orderBy('id')->get();

        if ($structures->isEmpty()) {
            return 0;
        }

        $alreadyInvoiced = Invoice::query()->where('term_id', $term->id)->pluck('student_id')->flip();

        $enrollments = Enrollment::query()
            ->where('academic_year_id', $year->id)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->whereHas('student', fn (Builder $q) => $q->where('status', StudentStatus::Active))
            ->orderBy('class_id')
            ->orderBy('roll_no')
            ->orderBy('id')
            ->get()
            ->reject(fn (Enrollment $enrollment): bool => $alreadyInvoiced->has($enrollment->student_id))
            ->values();

        if ($enrollments->isEmpty()) {
            return 0;
        }

        $studentIds = $enrollments->map(fn (Enrollment $enrollment): int => $enrollment->student_id)->all();
        $billedOnce = $this->billedOnce($structures, $studentIds);
        $returning = $this->returningStudents($studentIds, $year->id, $year->starts_on->toDateString());

        $today = $school->today();
        $issueYear = $today->year;
        $created = 0;

        foreach ($enrollments as $enrollment) {
            $items = $structures
                ->filter(fn (FeeStructure $fee): bool => $fee->class_id === null || $fee->class_id === $enrollment->class_id)
                ->filter(fn (FeeStructure $fee): bool => match ($fee->frequency) {
                    FeeFrequency::Term => true,
                    FeeFrequency::Annual => ! isset($billedOnce[$enrollment->student_id][$fee->id]),
                    FeeFrequency::OneOff => ! isset($billedOnce[$enrollment->student_id][$fee->id]) && ! isset($returning[$enrollment->student_id]),
                })
                ->values();

            if ($items->isEmpty()) {
                continue;
            }

            $dueOn = $term->starts_on->addDays((int) $items->min('due_day_offset'));

            try {
                $invoice = DB::transaction(function () use ($enrollment, $term, $items, $dueOn, $today, $school, $issueYear): Invoice {
                    $invoice = Invoice::query()->create([
                        'student_id' => $enrollment->student_id,
                        'term_id' => $term->id,
                        'class_id' => $enrollment->class_id,
                        'number' => $this->nextNumber->handle($school->id, $issueYear),
                        'total' => (int) $items->sum('amount'),
                        'status' => InvoiceStatus::Issued,
                        // Generated late (after the term started): due today at the earliest.
                        'due_on' => $dueOn->max($today),
                        'issued_at' => now(),
                    ]);

                    $invoice->items()->createMany($items->map(fn (FeeStructure $fee): array => [
                        'fee_structure_id' => $fee->id,
                        'description' => $fee->name,
                        'amount' => $fee->amount,
                    ])->all());

                    return $invoice;
                });
            } catch (UniqueConstraintViolationException) {
                // Invoiced in the meantime (e.g. by a run on another server): nothing to do.
                continue;
            }

            $created++;

            if ($notify) {
                SendInvoiceIssuedNotification::dispatch($school->id, $invoice->id);
            }
        }

        return $created;
    }

    /**
     * Annual and one-off fees already on a (non-void) invoice of the students, as
     * [student id => [fee structure id => true]].
     *
     * @param  Collection<int, FeeStructure>  $structures
     * @param  array<int, int>  $studentIds
     * @return array<int, array<int, true>>
     */
    private function billedOnce(Collection $structures, array $studentIds): array
    {
        $onceIds = $structures->where('frequency', '!=', FeeFrequency::Term)->modelKeys();

        if ($onceIds === []) {
            return [];
        }

        $billed = [];

        Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.student_id', $studentIds)
            ->whereIn('invoice_items.fee_structure_id', $onceIds)
            ->where('invoices.status', '!=', InvoiceStatus::Void)
            ->toBase()
            ->get(['invoices.student_id', 'invoice_items.fee_structure_id'])
            ->each(function (object $row) use (&$billed): void {
                /** @var object{student_id: int|string, fee_structure_id: int|string} $row */
                $billed[(int) $row->student_id][(int) $row->fee_structure_id] = true;
            });

        return $billed;
    }

    /**
     * Students enrolled in an earlier academic year, as [student id => true]: they pay no
     * one-off (admission) fees.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, true>
     */
    private function returningStudents(array $studentIds, int $yearId, string $yearStartsOn): array
    {
        $ids = Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('academic_year_id', '!=', $yearId)
            ->whereHas('academicYear', fn (Builder $q) => $q->where('starts_on', '<', $yearStartsOn))
            ->get(['student_id'])
            ->map(fn (Enrollment $enrollment): int => $enrollment->student_id)
            ->all();

        return array_fill_keys($ids, true);
    }
}
