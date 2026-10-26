<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles a member can hold within a school. Roles are global spatie roles; the assignment
 * is scoped to a school through spatie's teams mode (team id = school id).
 */
enum Role: string
{
    case SchoolAdmin = 'school_admin';
    case Teacher = 'teacher';
    case Parent = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::SchoolAdmin => __('School admin'),
            self::Teacher => __('Teacher'),
            self::Parent => __('Parent'),
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SchoolAdmin => [
                Permission::ManageSchool,
                Permission::ManageMembers,
                Permission::ManageStudents,
                Permission::TakeAttendance,
                Permission::EnterGrades,
                Permission::GenerateReportCards,
                Permission::ManageFees,
                Permission::ViewAuditLog,
                Permission::ManageDataProtection,
            ],
            self::Teacher => [
                Permission::TakeAttendance,
                Permission::EnterGrades,
                Permission::GenerateReportCards,
            ],
            self::Parent => [
                Permission::ViewOwnChildren,
            ],
        };
    }
}
