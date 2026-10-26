<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;

class SchoolPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * Listing all schools is a platform-level capability.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_platform_admin;
    }

    /**
     * Settings, setup wizard, academic structure. Only for the school currently acted in.
     */
    public function update(User $user, School $school): bool
    {
        return $this->currentSchool->idOrNull() === $school->id
            && $user->hasPermissionTo(Permission::ManageSchool);
    }

    /**
     * The data protection page: retention settings, exports and erasure.
     */
    public function manageDataProtection(User $user, School $school): bool
    {
        return $this->currentSchool->idOrNull() === $school->id
            && $user->hasPermissionTo(Permission::ManageDataProtection);
    }
}
