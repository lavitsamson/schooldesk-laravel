<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Sends a notification to the student's primary guardian, the family's contact for absences
 * and fees: to their portal account (mail and database) if they have one, else by mail.
 * Sent now: the callers are queued jobs.
 */
final class NotifyPrimaryGuardian
{
    /**
     * @return bool false when there is nobody to tell (no primary guardian, or no email)
     */
    public function handle(Student $student, Notification $notification): bool
    {
        /** @var Guardian|null $guardian */
        $guardian = $student->guardians()->wherePivot('is_primary', true)->with('user')->first();

        if ($guardian?->user !== null) {
            $guardian->user->notifyNow($notification);

            return true;
        }

        if ($guardian?->email !== null) {
            Notifier::route('mail', [$guardian->email => $guardian->name])->notifyNow($notification);

            return true;
        }

        return false;
    }
}
