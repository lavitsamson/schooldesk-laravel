<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportCardStatus;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Notifications\ReportCardPublished;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Tells every guardian of the student that the report card is out: guardians with a portal
 * account by mail and in the portal, the others by mail. The mail holds no grades, only the
 * news; the report card itself stays behind the portal login.
 */
final class NotifyGuardiansOfReportCard implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $reportCardId,
    ) {}

    public function handle(CurrentSchool $currentSchool): void
    {
        $currentSchool->run($this->schoolId, function (): void {
            $card = ReportCard::query()
                ->with(['school', 'term', 'student.guardians.user'])
                ->find($this->reportCardId);

            if ($card === null || $card->status !== ReportCardStatus::Published) {
                return;
            }

            $notification = new ReportCardPublished($card);

            $card->student->guardians->each(function (Guardian $guardian) use ($notification): void {
                if ($guardian->user !== null) {
                    $guardian->user->notifyNow($notification);
                } elseif ($guardian->email !== null) {
                    Notification::route('mail', [$guardian->email => $guardian->name])->notifyNow($notification);
                }
            });
        });
    }
}
