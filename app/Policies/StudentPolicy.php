<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

/**
 * Student records are managed by school admins. Teacher access (own classes) arrives with
 * attendance and grades; parent access (own children) with the parent portal.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * Bulk actions on the student list (move to class, mark as left, export).
     */
    public function updateMany(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * Promoting a year's students into the next academic year.
     */
    public function promote(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }
}
