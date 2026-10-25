<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportCards;

use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Support\TermChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The report cards of a class in a term (?term= or a "term" field): viewing them, generating
 * them and publishing the approved ones. For school admins and the class teacher.
 */
class ClassReportCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageClass', [ReportCard::class, $this->schoolClass()]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function schoolClass(): SchoolClass
    {
        /** @var SchoolClass */
        return $this->route('schoolClass');
    }

    public function term(): Term
    {
        return TermChoice::pick($this->schoolClass()->academicYear, $this->input('term'));
    }
}
