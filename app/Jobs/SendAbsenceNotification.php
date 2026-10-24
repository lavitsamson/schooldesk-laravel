<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Notifications\StudentAbsent;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Tells a student's primary guardian about an absence: by mail, and in the parent portal
 * (database notification) once the guardian has an account.
 *
 * At most once per student and day: the job claims the record (guardian_notified_at) under a
 * row lock before sending, so repeated saves, a mark flipped back and forth or a retried job
 * never send twice. If sending fails after the claim, the guardian is not notified again; a
 * missing mail is better than a duplicate here. A record no longer absent when the (delayed)
 * job runs is skipped.
 */
final class SendAbsenceNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $attendanceRecordId,
    ) {}

    public function handle(CurrentSchool $currentSchool): void
    {
        $currentSchool->run($this->schoolId, function (): void {
            $claimed = DB::transaction(function (): ?array {
                $record = AttendanceRecord::query()->lockForUpdate()->find($this->attendanceRecordId);

                if ($record === null || $record->status !== AttendanceStatus::Absent || $record->guardian_notified_at !== null) {
                    return null;
                }

                $record->load([
                    'school',
                    'schoolClass',
                    'student.guardians' => fn ($q) => $q->wherePivot('is_primary', true)->with('user'),
                ]);

                /** @var Guardian|null $guardian */
                $guardian = $record->student->guardians->first();

                if ($guardian === null || ($guardian->user === null && $guardian->email === null)) {
                    return null;
                }

                $record->forceFill(['guardian_notified_at' => now()])->save();

                return [$record, $guardian];
            });

            if ($claimed === null) {
                return;
            }

            [$record, $guardian] = $claimed;
            $notification = new StudentAbsent($record);

            if ($guardian->user !== null) {
                $guardian->user->notifyNow($notification);
            } else {
                Notification::route('mail', [(string) $guardian->email => $guardian->name])->notifyNow($notification);
            }
        });
    }
}
