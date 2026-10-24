<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ImportBatch;
use App\Models\User;

/**
 * Imports create and update student records, so they need the same permission as managing
 * students. Batches of other schools are never found (school scope), so they 404 first.
 */
class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }
}
