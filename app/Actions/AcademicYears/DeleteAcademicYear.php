<?php

declare(strict_types=1);

namespace App\Actions\AcademicYears;

use App\Models\AcademicYear;
use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

/**
 * Removes a year that was added by mistake, with its terms and classes. A year that students
 * are enrolled in is history and stays.
 */
final class DeleteAcademicYear
{
    public function handle(AcademicYear $year): void
    {
        if ($year->is_current) {
            throw ValidationException::withMessages(['year' => __('The current academic year cannot be deleted.')]);
        }

        if ($year->enrollments()->exists()) {
            throw ValidationException::withMessages(['year' => __('Students are enrolled in this year, so it cannot be deleted.')]);
        }

        if (Assessment::query()->whereIn('term_id', $year->terms()->select('id'))->exists()) {
            throw ValidationException::withMessages(['year' => __('Assessments have been set in this year, so it cannot be deleted.')]);
        }

        $year->delete();
    }
}
