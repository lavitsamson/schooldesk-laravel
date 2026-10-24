<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Capabilities inside a school. Platform-level rights are a flag on the user, not a permission.
 */
enum Permission: string
{
    case ManageSchool = 'school.manage';
    case ManageMembers = 'members.manage';
    case ManageStudents = 'students.manage';
    case TakeAttendance = 'attendance.take';
    case EnterGrades = 'grades.enter';
    case GenerateReportCards = 'report_cards.generate';
    case ManageFees = 'fees.manage';
    case ViewOwnChildren = 'children.view';
    case ViewAuditLog = 'audit.view';
}
