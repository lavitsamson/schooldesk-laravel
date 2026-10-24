<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class SaveAcademicYearRequest extends SetupStepRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'terms' => ['required', 'array', 'min:1', 'max:6'],
            'terms.*.name' => ['required', 'string', 'max:50', 'distinct'],
            'terms.*.starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'terms.*.ends_on' => ['required', 'date_format:Y-m-d', 'after:terms.*.starts_on', 'before_or_equal:ends_on'],
        ];
    }

    /**
     * Terms must be in order and must not overlap.
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

                /** @var list<array{starts_on: string, ends_on: string}> $terms */
                $terms = $this->input('terms');

                foreach ($terms as $index => $term) {
                    $previous = $terms[$index - 1] ?? null;

                    if ($previous !== null && $term['starts_on'] <= $previous['ends_on']) {
                        $validator->errors()->add(
                            "terms.{$index}.starts_on",
                            'Each term must start after the previous term ends.',
                        );
                    }
                }
            },
        ];
    }
}
