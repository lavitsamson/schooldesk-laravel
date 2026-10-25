<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The monthly attendance report as one sheet: a row per student with a status letter per day
 * (P, A, L, E), their totals and rate, and a class total row at the bottom. The report comes
 * from BuildMonthlyAttendanceReport. Strict null comparison keeps zero counts (by default
 * Laravel Excel writes 0 as an empty cell).
 */
final class AttendanceReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithTitle
{
    /**
     * @param  array{days: array<int, string>, students: array<int, array{roll_no: int|null, name: string, admission_no: string, statuses: array<string, string>, totals: array<string, int>, rate: float|null}>, totals: array{present: int, absent: int, late: int, excused: int, rate: float|null}}  $report
     */
    public function __construct(
        private readonly string $className,
        private readonly CarbonImmutable $month,
        private readonly array $report,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            __('Roll'),
            __('Name'),
            __('Admission no.'),
            ...array_map(fn (string $day): string => LocalDate::weekdayAndDay(CarbonImmutable::parse($day)), $this->report['days']),
            __('Present'),
            __('Absent'),
            __('Late'),
            __('Excused'),
            __('Attendance %'),
        ];
    }

    /**
     * @return array<int, list<int|float|string|null>>
     */
    public function array(): array
    {
        $rows = array_map(fn (array $student): array => [
            $student['roll_no'],
            $student['name'],
            $student['admission_no'],
            ...array_map(fn (string $day): string => $student['statuses'][$day] ?? '', $this->report['days']),
            $student['totals']['present'] ?? 0,
            $student['totals']['absent'] ?? 0,
            $student['totals']['late'] ?? 0,
            $student['totals']['excused'] ?? 0,
            $student['rate'],
        ], $this->report['students']);

        $totals = $this->report['totals'];
        $rows[] = [
            null,
            "Class {$this->className}",
            null,
            ...array_fill(0, count($this->report['days']), ''),
            $totals['present'],
            $totals['absent'],
            $totals['late'],
            $totals['excused'],
            $totals['rate'],
        ];

        return $rows;
    }

    public function title(): string
    {
        return LocalDate::monthAndYear($this->month);
    }
}
