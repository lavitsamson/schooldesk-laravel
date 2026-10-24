<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * Only guardians without linked students, so no child is left without a contact by accident.
     */
    public function delete(User $user, Guardian $guardian): bool
    {
        $hasStudents = $guardian->relationLoaded('students')
            ? $guardian->students->isNotEmpty()
            : $guardian->students()->exists();

        return $user->hasPermissionTo(Permission::ManageStudents) && ! $hasStudents;
    }

    /**
     * Inviting to the parent portal creates a school membership, so it also needs ManageMembers.
     */
    public function invite(User $user, Guardian $guardian): bool
    {
        return $guardian->user_id === null
            && $guardian->email !== null
            && $user->hasPermissionTo(Permission::ManageStudents)
            && $user->hasPermissionTo(Permission::ManageMembers);
    }
}
