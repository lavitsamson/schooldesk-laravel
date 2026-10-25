<?php

declare(strict_types=1);

namespace App\Http\Requests\Fees;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Generating a term's invoices by hand: any term of the current academic year.
 */
class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Invoice::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', 'integer'],
        ];
    }

    public function term(): Term
    {
        return TermChoice::pick(AcademicYear::query()->current()->firstOrFail(), $this->input('term'));
    }
}
