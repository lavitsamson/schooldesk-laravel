<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The user's notifications from the current school, newest first (ADR 0009: notifications
 * remember their school). A parent at two schools sees each school's in that school only.
 */
final class ListNotifications
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @return MorphMany<DatabaseNotification, User>
     */
    public function handle(User $user): MorphMany
    {
        return $user->notifications()
            ->where('school_id', $this->currentSchool->id())
            ->orderByDesc('id');
    }

    public function unreadCount(User $user): int
    {
        return $this->handle($user)->whereNull('read_at')->count();
    }
}
