<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * Attendance is taken per class. School admins may take and report on any class; teachers
 * only on their own (class teacher, or teaching a subject in it). Classes of other schools
 * are never found (school scope), so they 404 before this is asked.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::TakeAttendance);
    }

    public function take(User $user, SchoolClass $class): bool
    {
        return $this->ownsClass($user, $class);
    }

    /**
     * The monthly report and its export.
     */
    public function viewReport(User $user, SchoolClass $class): bool
    {
        return $this->ownsClass($user, $class);
    }

    /**
     * Whether the user sees every class, not only the ones they teach.
     */
    public function viewAllClasses(User $user): bool
    {
        return $user->hasPermissionTo(Permission::TakeAttendance)
            && $user->hasPermissionTo(Permission::ManageSchool);
    }

    private function ownsClass(User $user, SchoolClass $class): bool
    {
        return $this->viewAllClasses($user)
            || ($user->hasPermissionTo(Permission::TakeAttendance) && $class->isTaughtBy($user));
    }
}
