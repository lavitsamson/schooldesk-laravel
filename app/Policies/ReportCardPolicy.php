<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Report cards are written by the class teacher, for their own class, and by school admins
 * for every class: the same people who see the class results (GradePolicy). Subject teachers
 * and parents do not see drafts. Records of other schools are never found (school scope), so
 * they 404 before this is asked.
 */
class ReportCardPolicy
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permission::GenerateReportCards);
    }

    /**
     * Generating, writing, approving and publishing the report cards of a class.
     */
    public function manageClass(User $user, SchoolClass $class): bool
    {
        return $user->hasPermissionTo(Permission::GenerateReportCards)
            && ($user->hasPermissionTo(Permission::ManageSchool) || $class->class_teacher_id === $user->id);
    }

    public function view(User $user, ReportCard $reportCard): bool
    {
        return $this->manageClass($user, $reportCard->schoolClass);
    }

    public function update(User $user, ReportCard $reportCard): bool
    {
        return $this->manageClass($user, $reportCard->schoolClass);
    }

    /**
     * Asking the LLM for a comment draft: only where the school has turned the feature on.
     */
    public function draftComment(User $user, ReportCard $reportCard): bool
    {
        return $this->currentSchool->get()->aiCommentDraftsEnabled()
            && $this->update($user, $reportCard);
    }
}
