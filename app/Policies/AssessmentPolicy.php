<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;

/**
 * Assessments and their marks belong to a class-subject. School admins manage every one;
 * teachers only those they are assigned to teach (class_subject_teacher). Being class
 * teacher is not enough: the maths teacher enters the maths marks. Records of other schools
 * are never found (school scope), so they 404 before this is asked.
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::EnterGrades);
    }

    /**
     * Whether the user sees every class-subject, not only the ones they teach.
     */
    public function viewAllClasses(User $user): bool
    {
        return $user->hasPermissionTo(Permission::EnterGrades)
            && $user->hasPermissionTo(Permission::ManageSchool);
    }

    /**
     * The gradebook of a class-subject, and adding assessments to it.
     */
    public function manage(User $user, SchoolClass $class, Subject $subject): bool
    {
        return $this->viewAllClasses($user)
            || ($user->hasPermissionTo(Permission::EnterGrades) && $class->teachesSubject($user, $subject));
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $this->manage($user, $assessment->schoolClass, $assessment->subject);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $this->manage($user, $assessment->schoolClass, $assessment->subject);
    }

    public function enterGrades(User $user, Assessment $assessment): bool
    {
        return $this->manage($user, $assessment->schoolClass, $assessment->subject);
    }
}
