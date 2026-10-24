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
            ->subject("You're invited to {$school->name} on SchoolDesk")
            ->line("You have been invited to join {$school->name} on SchoolDesk.")
            ->action('Accept invitation', self::url($this->membership))
            ->line("This link expires in {$days} days.");
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
