<?php

declare(strict_types=1);

namespace App\Http\Requests\AcademicYears;

use App\Models\AcademicYear;
use App\Support\AcademicYearRules;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->year()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = AcademicYearRules::rules();
        $rules['name'][] = SchoolRule::unique('academic_years', 'name')->ignore($this->year());

        return [
            ...$rules,
            // Existing terms are sent back with their id; they must belong to this year.
            'terms.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('terms', 'id')->where('academic_year_id', $this->year()->id)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => AcademicYearRules::checkTermOrder($validator, $this->input('terms')),
        ];
    }

    public function year(): AcademicYear
    {
        /** @var AcademicYear */
        return $this->route('academicYear');
    }
}
