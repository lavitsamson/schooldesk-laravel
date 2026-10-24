<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * Term results across all subjects of a class, the basis of its report cards. School admins
 * see every class; teachers the classes they are class teacher of, since they write the
 * report cards. Subject teachers see their own subject's results in the gradebook.
 */
class GradePolicy
{
    public function viewResults(User $user, SchoolClass $class): bool
    {
        return $user->hasPermissionTo(Permission::GenerateReportCards)
            && ($user->hasPermissionTo(Permission::ManageSchool) || $class->class_teacher_id === $user->id);
    }
}
