<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Membership;
use App\Models\User;
use App\Support\CurrentSchool;

class MembershipPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageMembers);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageMembers);
    }

    public function resend(User $user, Membership $membership): bool
    {
        return $this->inCurrentSchool($membership)
            && ! $membership->isActive()
            && $user->hasPermissionTo(Permission::ManageMembers);
    }

    /**
     * Admins cannot remove themselves, which also guarantees a school always keeps an admin.
     */
    public function delete(User $user, Membership $membership): bool
    {
        return $this->inCurrentSchool($membership)
            && $membership->user_id !== $user->id
            && $user->hasPermissionTo(Permission::ManageMembers);
    }

    private function inCurrentSchool(Membership $membership): bool
    {
        return $membership->school_id === $this->currentSchool->idOrNull();
    }
}
