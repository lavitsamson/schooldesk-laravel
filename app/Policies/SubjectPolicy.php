<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }
}
