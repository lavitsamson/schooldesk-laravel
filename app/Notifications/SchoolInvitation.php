<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SchoolInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Membership $membership)
    {
        $this->afterCommit();

        // Queued, so sent without a school context: in the invitee's language if they chose
        // one, else in the school's.
        $this->locale($membership->user->locale ?? $membership->school->locale->value);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->membership->school;
        $days = (int) config('schooldesk.invitations.expire_after_days');

        return (new MailMessage)
            ->subject(__("You're invited to :school on SchoolDesk", ['school' => $school->name]))
            ->line(__('You have been invited to join :school on SchoolDesk.', ['school' => $school->name]))
            ->action(__('Accept invitation'), self::url($this->membership))
            ->line(__('This link expires in :days days.', ['days' => $days]));
    }

    /**
     * A signed, expiring link. It carries invited_at, so re-sending invalidates older links.
     */
    public static function url(Membership $membership): string
    {
        return URL::temporarySignedRoute(
            'invitations.show',
            now()->addDays((int) config('schooldesk.invitations.expire_after_days')),
            ['invitation' => $membership->id, 'sent' => $membership->invited_at?->getTimestamp()],
        );
    }
}
