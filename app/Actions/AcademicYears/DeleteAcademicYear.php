<?php

declare(strict_types=1);

namespace App\Actions\AcademicYears;

use App\Models\AcademicYear;
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
            throw ValidationException::withMessages(['year' => 'The current academic year cannot be deleted.']);
        }

        if ($year->enrollments()->exists()) {
            throw ValidationException::withMessages(['year' => 'Students are enrolled in this year, so it cannot be deleted.']);
        }

        $year->delete();
    }
}
