<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Gives a user a role in a specific school, whatever the current school context is.
 */
final class GrantRole
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(User $user, School $school, Role $role): void
    {
        $this->currentSchool->run($school, function () use ($user, $role): void {
            // The loaded roles relation may belong to another school.
            $user->unsetRelation('roles');
            $user->assignRole($role);
            $user->unsetRelation('roles');
        });
    }
}
