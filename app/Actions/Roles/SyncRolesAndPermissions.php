<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Enums\Permission;
use App\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the roles and permissions tables match the Role and Permission enums. Idempotent.
 *
 * Roles are global (school_id null); spatie's teams mode scopes each assignment to a school.
 */
final class SyncRolesAndPermissions
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(): void
    {
        $this->registrar->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web')
                ->syncPermissions(array_map(fn (Permission $p): string => $p->value, $role->permissions()));
        }

        $this->registrar->forgetCachedPermissions();
    }
}
