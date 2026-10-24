<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\User;
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
        $school = $this->record->school;
        $name = $this->record->student->first_name;

        return (new MailMessage)
            ->subject("{$name} was absent from school")
            ->line("{$name} was marked absent in {$this->record->schoolClass->displayName()} on {$this->date()}.")
            ->line("If you have not told {$school->name} about the absence yet, please contact the school.");
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

    private function date(): string
    {
        return $this->record->date->format('l, j F Y');
    }
}
