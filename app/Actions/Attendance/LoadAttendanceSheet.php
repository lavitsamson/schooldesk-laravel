<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Carbon\CarbonImmutable;

/**
 * The attendance grid of a class on a day: the students enrolled then, with what has been
 * recorded for them (status null = not taken yet; the grid then defaults to present).
 */
final class LoadAttendanceSheet
{
    /**
     * @return array{
     *     students: array<int, array{id: int, roll_no: int|null, name: string, admission_no: string, status: string|null, note: string|null}>,
     *     recorded: array{count: int, by: string|null, at: string|null},
     * }
     */
    public function handle(SchoolClass $class, CarbonImmutable $date): array
    {
        $enrollments = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeOn($date)
            ->with('student')
            ->orderByRaw('roll_no is null')
            ->orderBy('roll_no')
            ->get();

        $records = AttendanceRecord::query()
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->where('date', $date->toDateString())
            ->with('recordedBy')
            ->get()
            ->keyBy('student_id');

        $latest = $records->sortByDesc('updated_at')->first();

        return [
            'students' => $enrollments->map(function (Enrollment $enrollment) use ($records): array {
                /** @var AttendanceRecord|null $record */
                $record = $records->get($enrollment->student_id);

                return [
                    'id' => $enrollment->student->id,
                    'roll_no' => $enrollment->roll_no,
                    'name' => $enrollment->student->fullName(),
                    'admission_no' => $enrollment->student->admission_no,
                    'status' => $record?->status->value,
                    'note' => $record?->note,
                ];
            })->values()->all(),
            'recorded' => [
                'count' => $records->count(),
                'by' => $latest?->recordedBy?->name,
                'at' => $latest?->updated_at?->toIso8601String(),
            ],
        ];
    }
}
