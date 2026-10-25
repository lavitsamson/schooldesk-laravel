<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SchoolClass;
use Carbon\CarbonImmutable;

/**
 * Which days attendance can be taken for: days of the class's academic year, up to today
 * in the school's timezone. Weekends are allowed (Saturday school, trips) but skipped when
 * stepping from day to day.
 */
final class AttendanceDates
{
    /**
     * A strict Y-m-d date, or null (rejects 2026-02-31 instead of rolling it over).
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->toDateString() === $value ? $date : null;
    }

    /**
     * Why attendance cannot be taken for the date, or null if it can.
     */
    public static function problem(SchoolClass $class, CarbonImmutable $date): ?string
    {
        $year = $class->academicYear;

        return match (true) {
            $date->gt(app(CurrentSchool::class)->get()->today()) => (string) __('Attendance cannot be taken for a future date.'),
            $date->lt($year->starts_on) || $date->gt($year->ends_on) => (string) __(':date is outside the academic year :year.', [
                'date' => LocalDate::long($date),
                'year' => $year->name,
            ]),
            default => null,
        };
    }

    /**
     * The previous weekday the class can take attendance for, or null.
     */
    public static function previous(SchoolClass $class, CarbonImmutable $date): ?CarbonImmutable
    {
        $previous = $date->subDay();
        while ($previous->isWeekend()) {
            $previous = $previous->subDay();
        }

        return self::problem($class, $previous) === null ? $previous : null;
    }

    /**
     * The next weekday the class can take attendance for, or null.
     */
    public static function next(SchoolClass $class, CarbonImmutable $date): ?CarbonImmutable
    {
        $next = $date->addDay();
        while ($next->isWeekend()) {
            $next = $next->addDay();
        }

        return self::problem($class, $next) === null ? $next : null;
    }
}
