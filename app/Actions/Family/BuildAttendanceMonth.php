<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Student;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;

/**
 * A child's attendance in one calendar month, for the portal's calendar and the API: every day
 * of the month with the status taken that day (null: no school, a weekend, or not taken yet),
 * the totals and the attendance rate. Present and late count as attended, as in the class
 * report (AttendanceStatus::countsAsAttended()). Teachers' notes stay with the school.
 *
 * @phpstan-type AttendanceMonth array{
 *     month: string, previous: string, next: string|null,
 *     days: list<array{date: string, weekday: int, status: string|null}>,
 *     totals: array<string, int>, recorded: int, rate: float|null,
 * }
 */
final class BuildAttendanceMonth
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @return AttendanceMonth
     */
    public function handle(Student $student, CarbonImmutable $month): array
    {
        $first = $month->startOfMonth()->startOfDay();
        $last = $first->endOfMonth()->startOfDay();
        $thisMonth = $this->currentSchool->get()->today()->startOfMonth();

        /** @var array<string, AttendanceStatus> $statuses */
        $statuses = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()])
            ->get(['date', 'status'])
            ->mapWithKeys(fn (AttendanceRecord $record): array => [$record->date->toDateString() => $record->status])
            ->all();

        $days = [];
        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->dayOfWeekIso,
                'status' => ($statuses[$day->toDateString()] ?? null)?->value,
            ];
        }

        $totals = [];
        foreach (AttendanceStatus::cases() as $status) {
            $totals[$status->value] = count(array_filter($statuses, fn (AttendanceStatus $s): bool => $s === $status));
        }

        $attended = count(array_filter($statuses, fn (AttendanceStatus $s): bool => $s->countsAsAttended()));

        return [
            'month' => $first->format('Y-m'),
            'previous' => $first->subMonth()->format('Y-m'),
            'next' => $first->lt($thisMonth) ? $first->addMonth()->format('Y-m') : null,
            'days' => $days,
            'totals' => $totals,
            'recorded' => count($statuses),
            'rate' => $statuses === [] ? null : round($attended / count($statuses) * 100, 1),
        ];
    }
}
