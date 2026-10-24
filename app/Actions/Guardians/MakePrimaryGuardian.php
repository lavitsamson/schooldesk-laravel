<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Each student has exactly one primary guardian (once they have any).
 */
final class MakePrimaryGuardian
{
    public function handle(Student $student, Guardian $guardian): void
    {
        DB::transaction(function () use ($student, $guardian): void {
            $link = GuardianStudent::query()
                ->where('student_id', $student->id)
                ->where('guardian_id', $guardian->id)
                ->firstOrFail();

            GuardianStudent::query()
                ->where('student_id', $student->id)
                ->whereKeyNot($link->id)
                ->update(['is_primary' => false]);

            $link->update(['is_primary' => true]);
        });
    }
}
