<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\StudentExport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the admin who asked for a student data export. The link is signed and expires with
 * the export; the download also needs the admin to be signed in.
 */
class StudentExportReady extends Notification
{
    public function __construct(public readonly StudentExport $export) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = [
            'name' => $this->export->student->fullName(),
            'hours' => (string) config('schooldesk.gdpr.export_link_hours'),
        ];

        return (new MailMessage)
            ->subject(__('Data export ready: :name', $replace))
            ->line(__('The data export for :name is ready: their details, attendance, marks, report cards and invoices.', $replace))
            ->action(__('Download the export'), URL::temporarySignedRoute(
                'student-exports.download',
                $this->export->expires_at ?? now(),
                ['studentExport' => $this->export->id],
            ))
            ->line(__('The link works for :hours hours. The file holds personal data: store it safely and delete it once it has been handed over.', $replace));
    }
}
