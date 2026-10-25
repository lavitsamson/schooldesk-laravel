<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\User;

/**
 * School admins manage every invoice of their school. Parents see and pay the invoices of
 * their own children only (as a guardian with a portal account). Records of other schools are
 * never found (school scope), so they 404 before this is asked.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }

    /**
     * The invoices of one's own children, for parents.
     */
    public function viewFamily(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ViewOwnChildren);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees) || $this->isGuardianOf($user, $invoice);
    }

    /**
     * Generating term invoices, recording cash and bank payments, voiding.
     */
    public function manage(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees) && $invoice->status->isOpen();
    }

    /**
     * Only invoices nobody has paid anything on yet: a payment has to be refunded first.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo(Permission::ManageFees) && $invoice->status === InvoiceStatus::Issued;
    }

    /**
     * Paying online is for the child's guardians; staff record payments instead.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        return $invoice->status->isOpen() && $this->isGuardianOf($user, $invoice);
    }

    private function isGuardianOf(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo(Permission::ViewOwnChildren)
            && Guardian::query()
                ->where('user_id', $user->id)
                ->whereHas('students', fn ($query) => $query->whereKey($invoice->student_id))
                ->exists();
    }
}
