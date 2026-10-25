<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }

    /**
     * Invoices keep their items (description and amount) when the fee structure goes.
     */
    public function delete(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }
}
