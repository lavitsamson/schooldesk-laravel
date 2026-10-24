<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * The school's grade scale is a school setting.
 */
class GradeScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }

    public function update(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageSchool);
    }
}
