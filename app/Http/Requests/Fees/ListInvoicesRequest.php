<?php

declare(strict_types=1);

namespace App\Http\Requests\Fees;

use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the invoice list. Besides the stored statuses, "open" (anything left to pay)
 * and "overdue" (open and past the due date) are offered.
 */
class ListInvoicesRequest extends FormRequest
{
    public const ALL = 'all';

    public const OPEN = 'open';

    public const OVERDUE = 'overdue';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Invoice::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([self::ALL, self::OPEN, self::OVERDUE, ...array_column(InvoiceStatus::cases(), 'value')])],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function year(): AcademicYear
    {
        return AcademicYear::query()->current()->with('terms')->firstOrFail();
    }

    public function term(): Term
    {
        return TermChoice::pick($this->year(), $this->query('term'));
    }

    /**
     * @return array{term_id: int, class_id: int|null, status: string, search: string}
     */
    public function filters(Term $term): array
    {
        return [
            'term_id' => $term->id,
            'class_id' => $this->filled('class_id') ? $this->integer('class_id') : null,
            'status' => $this->string('status')->toString() ?: self::ALL,
            'search' => trim($this->string('search')->toString()),
        ];
    }
}
