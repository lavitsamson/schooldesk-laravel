<?php

declare(strict_types=1);

namespace App\Actions\AcademicYears;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

/**
 * Switches the school to another academic year, e.g. after promoting students into it.
 * Lists, attendance and grades all follow the current year.
 */
final class MakeAcademicYearCurrent
{
    public function handle(AcademicYear $year): void
    {
        DB::transaction(function () use ($year): void {
            AcademicYear::query()->current()->whereKeyNot($year->id)->update(['is_current' => false]);
            $year->update(['is_current' => true]);
        });
    }
}
