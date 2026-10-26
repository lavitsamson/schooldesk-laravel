<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * The audit log is for school admins. Entries are never changed or deleted through the app.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ViewAuditLog);
    }
}
