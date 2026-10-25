<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by the queued NotifyGuardiansOfReportCard job, so not queued itself. First name only,
 * and no grades: the report card is read in the parent portal.
 */
class ReportCardPublished extends Notification
{
    public function __construct(public readonly ReportCard $reportCard) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->reportCard->student->first_name;
        $term = $this->reportCard->term->name;

        return (new MailMessage)
            ->subject("{$name}'s report card for the {$term}")
            ->line("{$this->reportCard->school->name} has published {$name}'s report card for the {$term}.")
            ->line('You can read and download it in the parent portal.')
            ->action('Open the parent portal', route('dashboard'));
    }

    /**
     * @return array{report_card_id: int, student_id: int, first_name: string, term: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_card_id' => $this->reportCard->id,
            'student_id' => $this->reportCard->student_id,
            'first_name' => $this->reportCard->student->first_name,
            'term' => $this->reportCard->term->name,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'report-card-published';
    }
}
