<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Support\CurrentSchool;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The database channel, with the school the notification was sent in. Notifications go out
 * from queued jobs that set their school, so a parent at two schools sees each school's
 * notifications in that school only (ADR 0009). Bound in place of Laravel's channel.
 */
class SchoolDatabaseChannel extends DatabaseChannel
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'school_id' => $this->currentSchool->idOrNull(),
        ];
    }
}
