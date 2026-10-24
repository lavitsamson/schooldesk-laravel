<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * Classes and their teacher assignments, managed by school admins. Teachers reach their own
 * classes through attendance and grade entry, not through these screens.
 */
class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }
}
