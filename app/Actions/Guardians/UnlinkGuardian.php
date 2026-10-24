<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Removes the link only; the guardian record stays (they may have other children here).
 * If the primary guardian is unlinked, the longest-linked remaining guardian takes over.
 */
final class UnlinkGuardian
{
    public function handle(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian): void {
            $student->guardians()->detach($guardian->id);

            $links = GuardianStudent::query()->where('student_id', $student->id);

            if ((clone $links)->exists() && ! (clone $links)->where('is_primary', true)->exists()) {
                (clone $links)->orderBy('id')->firstOrFail()->update(['is_primary' => true]);
            }
        });
    }
}
