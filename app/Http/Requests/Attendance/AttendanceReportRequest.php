<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /attendance/{schoolClass}/report (and its export) for ?month=YYYY-MM, one of the
 * months of the class's academic year up to now. Defaults to the latest of them.
 */
class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewReport', [AttendanceRecord::class, $this->schoolClass()]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', Rule::in($this->months())],
        ];
    }

    public function schoolClass(): SchoolClass
    {
        /** @var SchoolClass */
        return $this->route('schoolClass');
    }

    public function month(): CarbonImmutable
    {
        $months = $this->months();
        $month = $this->filled('month') ? $this->string('month')->toString() : end($months);

        return CarbonImmutable::createFromFormat('!Y-m', (string) $month) ?? abort(404);
    }

    /**
     * Months of the academic year, from its first up to the current one (YYYY-MM).
     *
     * @return non-empty-list<string>
     */
    public function months(): array
    {
        $year = $this->schoolClass()->academicYear;
        $last = app(CurrentSchool::class)->get()->today()->min($year->ends_on)->max($year->starts_on)->startOfMonth();
        $months = [];

        for ($month = $year->starts_on->startOfMonth(); $month->lte($last); $month = $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        return $months === [] ? [$last->format('Y-m')] : $months;
    }
}
