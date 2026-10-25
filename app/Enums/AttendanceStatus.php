<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Absent => __('Absent'),
            self::Late => __('Late'),
            self::Excused => __('Excused'),
        };
    }

    /**
     * One letter for the monthly report grid and its export.
     */
    public function code(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::Late => 'L',
            self::Excused => 'E',
        };
    }

    /**
     * Late students were at school, so they count towards the attendance rate. Excused
     * absences do not, but they never notify guardians (the school already knows why).
     */
    public function countsAsAttended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
