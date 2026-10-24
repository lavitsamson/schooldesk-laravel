<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Exceptions\CrossSchoolWriteException;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Links a guardian to a student. The first guardian becomes the primary contact (who gets
 * absence notifications); later ones only if asked.
 */
final class LinkGuardian
{
    public function __construct(private readonly MakePrimaryGuardian $makePrimary) {}

    public function handle(Student $student, Guardian $guardian, bool $primary = false): void
    {
        // guardian_student has no school_id of its own, so this is the only place that keeps
        // a link from joining two schools.
        if ($guardian->school_id !== $student->school_id) {
            throw CrossSchoolWriteException::forModel(Guardian::class);
        }

        DB::transaction(function () use ($student, $guardian, $primary): void {
            $student->guardians()->syncWithoutDetaching([$guardian->id]);

            $hasPrimary = $student->guardians()->wherePivot('is_primary', true)->exists();

            if ($primary || ! $hasPrimary) {
                $this->makePrimary->handle($student, $guardian);
            }
        });
    }
}
