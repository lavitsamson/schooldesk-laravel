<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Fees\ListInvoicesRequest;
use App\Models\Invoice;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The invoices of a term for school admins: search by student or invoice number, filter by
 * class and status (including the derived "overdue" and "open"), paginated on the server.
 */
final class ListInvoices
{
    public const PER_PAGE = 25;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @param  array{term_id: int, class_id: int|null, status: string, search: string}  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $today = $this->currentSchool->get()->today();

        return Invoice::query()
            ->with(['student', 'schoolClass'])
            ->withPaidAmount()
            ->where('term_id', $filters['term_id'])
            ->when($filters['class_id'] !== null, fn (Builder $q) => $q->where('class_id', $filters['class_id']))
            ->when($filters['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('student', fn (Builder $s) => $s->search($filters['search']))
                ->orWhereRaw('lower(number) like ?', ['%'.mb_strtolower($filters['search']).'%'])))
            ->tap(fn (Builder $q) => match ($filters['status']) {
                ListInvoicesRequest::ALL => null,
                ListInvoicesRequest::OPEN => $q->whereIn('status', InvoiceStatus::open()),
                ListInvoicesRequest::OVERDUE => $q->overdueOn($today),
                default => $q->where('status', $filters['status']),
            })
            ->orderBy('number')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
