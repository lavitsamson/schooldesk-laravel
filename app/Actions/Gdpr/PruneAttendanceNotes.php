<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Models\AttendanceRecord;
use App\Models\School;
use App\Support\CurrentSchool;

/**
 * Deletes the school's attendance notes older than its retention setting. The records stay
 * (attendance rates on report cards need them); only the free-text note goes. Returns how many
 * notes were deleted.
 */
final class PruneAttendanceNotes
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(School $school): int
    {
        $months = $school->attendanceNoteRetentionMonths();

        if ($months === null) {
            return 0;
        }

        return $this->currentSchool->run($school, fn (): int => AttendanceRecord::query()
            ->whereNotNull('note')
            ->where('date', '<', $school->today()->subMonthsNoOverflow($months)->toDateString())
            ->update(['note' => null]));
    }
}
