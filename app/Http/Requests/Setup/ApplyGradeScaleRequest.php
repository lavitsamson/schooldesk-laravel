<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use App\Enums\GradeScalePreset;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The last step: also checks the earlier steps were done before setup can be completed.
 */
class ApplyGradeScaleRequest extends SetupStepRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => ['required', Rule::enum(GradeScalePreset::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $missing = array_keys(array_filter([
                    'an academic year' => ! AcademicYear::query()->where('is_current', true)->exists(),
                    'at least one class' => ! SchoolClass::query()->exists(),
                    'at least one subject' => ! Subject::query()->exists(),
                ]));

                if ($missing !== []) {
                    $validator->errors()->add('preset', 'Before finishing, add '.implode(', ', $missing).'.');
                }
            },
        ];
    }

    public function preset(): GradeScalePreset
    {
        return $this->enum('preset', GradeScalePreset::class) ?? GradeScalePreset::LetterAToF;
    }
}
