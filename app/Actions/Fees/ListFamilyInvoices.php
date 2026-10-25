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
     * The query, for the API to paginate.
     *
     * @return Builder<Invoice>
     */
    public function query(User $parent): Builder
    {
        return Invoice::query()
            ->with(['student', 'term'])
            ->withPaidAmount()
            ->whereIn('student_id', Student::query()->childrenOf($parent)->select('id'))
            ->where('status', '!=', InvoiceStatus::Void)
            ->orderByDesc('issued_at')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function handle(User $parent): Collection
    {
        return $this->query($parent)->get();
    }
}
