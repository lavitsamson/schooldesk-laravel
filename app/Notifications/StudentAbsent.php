<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by the queued SendAbsenceNotification job, so not queued itself. Holds the first name
 * only: enough for the guardian to recognise their child.
 */
class StudentAbsent extends Notification
{
    public function __construct(public readonly AttendanceRecord $record) {}

    /**
     * Guardians with a portal account also see it there; others get the mail only.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = [
            'name' => $this->record->student->first_name,
            'class' => $this->record->schoolClass->displayName(),
            'date' => LocalDate::withWeekday($this->record->date),
            'school' => $this->record->school->name,
        ];

        return (new MailMessage)
            ->subject(__(':name was absent from school', $replace))
            ->line(__(':name was marked absent in :class on :date.', $replace))
            ->line(__('If you have not told :school about the absence yet, please contact the school.', $replace));
    }

    /**
     * @return array{student_id: int, first_name: string, class: string, date: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->record->student_id,
            'first_name' => $this->record->student->first_name,
            'class' => $this->record->schoolClass->displayName(),
            'date' => $this->record->date->toDateString(),
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'student-absent';
    }
}
