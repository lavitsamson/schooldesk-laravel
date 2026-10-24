<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Jobs\SendAbsenceNotification;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Saves a class's attendance for a day, all or nothing. Saving again updates the day's
 * records (one per student and day) instead of adding new ones.
 *
 * Every student newly marked absent queues a notification to their primary guardian. It is
 * delayed a few minutes, so a wrong click corrected straight away never reaches a parent,
 * and the job sends at most one per student and day (see SendAbsenceNotification).
 */
final class RecordAttendance
{
    /**
     * @param  list<array{student_id: int, status: AttendanceStatus, note: string|null}>  $entries
     * @return array{saved: int, absent: int}
     */
    public function handle(SchoolClass $class, CarbonImmutable $date, array $entries, User $recordedBy): array
    {
        return DB::transaction(function () use ($class, $date, $entries, $recordedBy): array {
            $existing = AttendanceRecord::query()
                ->whereIn('student_id', array_column($entries, 'student_id'))
                ->where('date', $date->toDateString())
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            $absent = 0;

            foreach ($entries as $entry) {
                $record = $existing->get($entry['student_id']) ?? new AttendanceRecord([
                    'student_id' => $entry['student_id'],
                    'date' => $date->toDateString(),
                ]);
                $wasAbsent = $record->exists && $record->status === AttendanceStatus::Absent;

                $record->fill([
                    'class_id' => $class->id,
                    'status' => $entry['status'],
                    'note' => $entry['note'],
                ]);

                if ($record->isDirty()) {
                    $record->recorded_by = $recordedBy->id;
                    $record->save();
                }

                if ($entry['status'] === AttendanceStatus::Absent) {
                    $absent++;

                    if (! $wasAbsent && $record->guardian_notified_at === null) {
                        SendAbsenceNotification::dispatch($class->school_id, $record->id)
                            ->delay(now()->addMinutes((int) config('schooldesk.attendance.absence_notification_delay_minutes')))
                            ->afterCommit();
                    }
                }
            }

            return ['saved' => count($entries), 'absent' => $absent];
        });
    }
}
