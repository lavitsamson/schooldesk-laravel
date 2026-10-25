<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The invoices of a parent's children in the current school, newest first. Children are found
 * through the parent's guardian records, so a parent never sees another family's invoices.
 * Void invoices are left out: there is nothing for the parent to do with them.
 */
final class ListFamilyInvoices
{
    /**
     * @return Collection<int, Invoice>
     */
    public function handle(User $parent): Collection
    {
        $children = Student::query()
            ->whereHas('guardians', fn (Builder $q) => $q->where('user_id', $parent->id))
            ->select('id');

        return Invoice::query()
            ->with(['student', 'term'])
            ->withPaidAmount()
            ->whereIn('student_id', $children)
            ->where('status', '!=', InvoiceStatus::Void)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();
    }
}
