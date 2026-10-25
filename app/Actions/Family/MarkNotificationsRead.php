<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Marks one notification, or all of the current school's, as read. A notification of another
 * user or another school is not found (404), like any other record.
 */
final class MarkNotificationsRead
{
    public function __construct(private readonly ListNotifications $notifications) {}

    /**
     * @throws ModelNotFoundException<DatabaseNotification>
     */
    public function handle(User $user, ?string $id = null): ?DatabaseNotification
    {
        if ($id === null) {
            $this->notifications->handle($user)->whereNull('read_at')->update(['read_at' => now()]);

            return null;
        }

        $notification = $this->notifications->handle($user)->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }
}
