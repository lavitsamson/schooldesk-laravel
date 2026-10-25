<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\CurrentSchool;

/**
 * Collected against outstanding for a term, per class and in total, aggregated in the
 * database (one grouped query, whatever the number of invoices). Amounts are minor units.
 * Void invoices are left out; an overpaid invoice counts as collected but never as a negative
 * outstanding amount.
 *
 * @phpstan-type Totals array{invoices: int, invoiced: int, collected: int, outstanding: int, overdue: int, paid: int}
 * @phpstan-type ClassTotals array{id: int|null, name: string, invoices: int, invoiced: int, collected: int, outstanding: int, overdue: int, paid: int}
 */
final class BuildFeesDashboard
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @return array{classes: array<int, ClassTotals>, totals: Totals}
     */
    public function handle(Term $term): array
    {
        $today = $this->currentSchool->get()->today()->toDateString();
        $open = array_map(fn (InvoiceStatus $s): string => $s->value, InvoiceStatus::open());

        $paid = Payment::query()
            ->selectRaw('invoice_id, sum(amount - refunded_amount) as paid')
            ->groupBy('invoice_id');

        $rows = Invoice::query()
            ->where('invoices.term_id', $term->id)
            ->where('invoices.status', '!=', InvoiceStatus::Void)
            ->leftJoinSub($paid, 'p', 'p.invoice_id', '=', 'invoices.id')
            ->groupBy('invoices.class_id')
            ->toBase()
            ->selectRaw(
                'invoices.class_id,
                count(*) as invoices,
                sum(invoices.total) as invoiced,
                sum(coalesce(p.paid, 0)) as collected,
                sum(case when invoices.total > coalesce(p.paid, 0) then invoices.total - coalesce(p.paid, 0) else 0 end) as outstanding,
                sum(case when invoices.status in (?, ?) and invoices.due_on < ? then invoices.total - coalesce(p.paid, 0) else 0 end) as overdue,
                sum(case when invoices.status = ? then 1 else 0 end) as paid',
                [...$open, $today, InvoiceStatus::Paid->value],
            )
            ->get();

        $names = SchoolClass::query()
            ->whereIn('id', $rows->pluck('class_id')->filter()->all())
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [$class->id => $class->displayName()]);

        $classes = $rows
            ->map(function (object $row) use ($names): array {
                /** @var object{class_id: int|string|null, invoices: int|string, invoiced: int|string, collected: int|string, outstanding: int|string, overdue: int|string, paid: int|string} $row */
                $id = $row->class_id === null ? null : (int) $row->class_id;

                return [
                    'id' => $id,
                    'name' => $id === null ? 'No class' : (string) $names->get($id, 'Deleted class'),
                    'invoices' => (int) $row->invoices,
                    'invoiced' => (int) $row->invoiced,
                    'collected' => (int) $row->collected,
                    'outstanding' => (int) $row->outstanding,
                    'overdue' => (int) $row->overdue,
                    'paid' => (int) $row->paid,
                ];
            })
            ->sortBy('name', SORT_NATURAL)
            ->values();

        $totals = [];
        foreach (['invoices', 'invoiced', 'collected', 'outstanding', 'overdue', 'paid'] as $key) {
            $totals[$key] = (int) $classes->sum($key);
        }

        /** @var Totals $totals */
        return ['classes' => $classes->all(), 'totals' => $totals];
    }
}
