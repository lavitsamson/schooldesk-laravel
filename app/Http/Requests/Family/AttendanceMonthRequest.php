<?php

declare(strict_types=1);

namespace App\Http\Requests\Family;

use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A child's attendance in a month: ?month=2026-10, by default this month in the school's
 * timezone. Months in the future are refused: there is nothing to show yet.
 */
class AttendanceMonthRequest extends ChildRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => [
                'nullable',
                'date_format:Y-m',
                // "2026-11" > "2026-10" as strings; checked late, so the school is known.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && $value > $this->thisMonth()->format('Y-m')) {
                        $fail('The month cannot be in the future.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.date_format' => 'The month must look like 2026-10.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function queryParameters(): array
    {
        return [
            'month' => ['description' => 'The month, YYYY-MM. Defaults to the current month; future months are refused.', 'example' => '2026-10'],
        ];
    }

    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return is_string($month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month) ?: abort(422)
            : $this->thisMonth();
    }

    private function thisMonth(): CarbonImmutable
    {
        return app(CurrentSchool::class)->get()->today()->startOfMonth();
    }
}
