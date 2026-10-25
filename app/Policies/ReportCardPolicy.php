<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;

/**
 * Report cards are written by the class teacher, for their own class, and by school admins
 * for every class: the same people who see the class results (GradePolicy). Subject teachers
 * do not see them, parents only once published. Records of other schools are never found
 * (school scope), so they 404 before this is asked.
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

    /**
     * The PDF: for those who write the report card, and once published for the student's
     * guardians (the parent portal and the API).
     */
    public function download(User $user, ReportCard $reportCard): bool
    {
        return $this->view($user, $reportCard) || $this->viewAsGuardian($user, $reportCard);
    }

    /**
     * Parents only ever see published report cards of their own children.
     */
    public function viewAsGuardian(User $user, ReportCard $reportCard): bool
    {
        return $reportCard->status === ReportCardStatus::Published
            && $user->hasPermissionTo(Permission::ViewOwnChildren)
            && Student::query()->childrenOf($user)->whereKey($reportCard->student_id)->exists();
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
