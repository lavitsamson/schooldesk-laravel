<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Models\School;

/**
 * How long the school keeps attendance notes; null keeps them. The nightly
 * gdpr:prune-attendance-notes run deletes older ones.
 */
final class UpdateAttendanceNoteRetention
{
    public function handle(School $school, ?int $months): void
    {
        $school->update(['settings' => [...($school->settings ?? []), School::SETTING_ATTENDANCE_NOTE_RETENTION_MONTHS => $months]]);
    }
}
