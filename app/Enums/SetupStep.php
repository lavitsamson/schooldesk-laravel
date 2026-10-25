<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The steps of the school setup wizard, in order.
 */
enum SetupStep: string
{
    case SchoolDetails = 'school';
    case AcademicYear = 'academic-year';
    case Classes = 'classes';
    case Subjects = 'subjects';
    case GradeScale = 'grade-scale';

    public function label(): string
    {
        return match ($this) {
            self::SchoolDetails => __('School details'),
            self::AcademicYear => __('Academic year & terms'),
            self::Classes => __('Classes'),
            self::Subjects => __('Subjects'),
            self::GradeScale => __('Grade scale'),
        };
    }

    public function component(): string
    {
        return match ($this) {
            self::SchoolDetails => 'Setup/SchoolDetails',
            self::AcademicYear => 'Setup/AcademicYear',
            self::Classes => 'Setup/Classes',
            self::Subjects => 'Setup/Subjects',
            self::GradeScale => 'Setup/GradeScale',
        };
    }

    public function next(): ?self
    {
        $steps = self::cases();
        $index = array_search($this, $steps, true);

        return $steps[$index + 1] ?? null;
    }
}
