<?php

declare(strict_types=1);

namespace App\Http\Requests\AcademicYears;

use App\Models\AcademicYear;
use App\Support\AcademicYearRules;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AcademicYear::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = AcademicYearRules::rules();
        $rules['name'][] = SchoolRule::unique('academic_years', 'name');

        return [
            ...$rules,
            'copy_classes_from' => ['nullable', 'integer', SchoolRule::exists('academic_years')],
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

    public function copyClassesFrom(): ?AcademicYear
    {
        return $this->filled('copy_classes_from')
            ? AcademicYear::query()->findOrFail($this->integer('copy_classes_from'))
            : null;
    }
}
