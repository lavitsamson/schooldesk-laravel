<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

/**
 * Student records are managed by school admins. Parents see their own children only, in the
 * parent portal and the API (viewAsGuardian). Teachers reach students through their classes
 * (AttendancePolicy, GradePolicy). Students of other schools are never found (school scope),
 * so they 404 before this is asked.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * The parent portal and the API: a parent's list of their own children.
     */
    public function viewFamily(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ViewOwnChildren);
    }

    /**
     * One of the parent's own children: attendance, results and report cards.
     */
    public function viewAsGuardian(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ViewOwnChildren)
            && Student::query()->childrenOf($user)->whereKey($student->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * An anonymised student's record is kept for grades and finances only: it is not edited
     * again (nor linked to guardians), so no personal data comes back.
     */
    public function update(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents) && $student->anonymised_at === null;
    }

    /**
     * GDPR access request: a ZIP of everything held about the student, emailed as a link.
     */
    public function exportData(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageDataProtection) && $student->anonymised_at === null;
    }

    /**
     * GDPR erasure. Cannot be undone, and is done once.
     */
    public function anonymise(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageDataProtection) && $student->anonymised_at === null;
    }

    /**
     * Bulk actions on the student list (move to class, mark as left, export).
     */
    public function updateMany(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    /**
     * Promoting a year's students into the next academic year.
     */
    public function promote(User $user): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermissionTo(Permission::ManageStudents);
    }
}
