<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AcademicYear;
use App\Models\User;

/**
 * Academic years and terms are part of the school's structure, managed by school admins.
 * Records are already limited to the current school by BelongsToSchool.
 */
class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function update(User $user, AcademicYear $year): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function delete(User $user, AcademicYear $year): bool
    {
        return ! $year->is_current && $user->hasPermissionTo(Permission::ManageSchool);
    }
}
