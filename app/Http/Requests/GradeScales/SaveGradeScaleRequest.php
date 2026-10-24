<?php

declare(strict_types=1);

namespace App\Http\Requests\GradeScales;

use App\Models\GradeScale;
use App\Support\Grading\Decimal;
use App\Support\Grading\GradeBands;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /grade-scale: the whole scale at once.
 */
class SaveGradeScaleRequest extends FormRequest
{
    public const MAX_BANDS = 15;

    public function authorize(): bool
    {
        return $this->user()?->can('update', GradeScale::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bands' => ['required', 'array', 'min:2', 'max:'.self::MAX_BANDS],
            'bands.*.label' => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'bands.*.min_percent' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'bands.*.max_percent' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
        ];
    }

    /**
     * The bands must cover 0–100 % without gaps or overlaps.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (GradeBands::problems($this->bands()) as $problem) {
                    $validator->errors()->add('bands', $problem);
                }
            },
        ];
    }

    /**
     * @return list<array{label: string, min_percent: string, max_percent: string}>
     */
    public function bands(): array
    {
        /** @var array<int, array{label: string, min_percent: string|int|float, max_percent: string|int|float}> $bands */
        $bands = (array) $this->input('bands', []);

        return array_values(array_map(fn (array $band): array => [
            'label' => trim($band['label']),
            'min_percent' => Decimal::fixed($band['min_percent']),
            'max_percent' => Decimal::fixed($band['max_percent']),
        ], $bands));
    }
}
