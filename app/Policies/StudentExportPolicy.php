<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StudentExport;
use App\Models\User;

/**
 * Exports hold everything about a student, so only data protection admins download them, and
 * only through the signed link in the mail. Other schools' exports 404 first (school scope).
 */
class StudentExportPolicy
{
    public function download(User $user, StudentExport $export): bool
    {
        return $user->hasPermissionTo(Permission::ManageDataProtection);
    }
}
