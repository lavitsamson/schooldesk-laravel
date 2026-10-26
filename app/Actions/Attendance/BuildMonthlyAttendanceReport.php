<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A class's attendance in one month: a student-by-day grid with totals per student, and the
 * daily counts for the chart. Days are those on which attendance was taken in the class.
 *
 * The rate counts present and late as attended (AttendanceStatus::countsAsAttended()) and is
 * a percentage of the days recorded for that student, so a student who joined mid-month is
 * not penalised for the days before.
 */
final class BuildMonthlyAttendanceReport
{
    /**
     * @return array{
     *     days: array<int, string>,
     *     students: array<int, array{id: int, roll_no: int|null, name: string, admission_no: string, statuses: array<string, string>, totals: array<string, int>, rate: float|null}>,
     *     daily: array<int, array{date: string, present: int, absent: int, late: int, excused: int}>,
     *     totals: array{present: int, absent: int, late: int, excused: int, rate: float|null},
     * }
     */
    public function handle(SchoolClass $class, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->startOfDay();

        // Plain rows, not models: a class's month is about a thousand records, and casting each
        // record's date and status on every use made up most of the page's time.
        /** @var Collection<int, array{student_id: int, date: string, status: AttendanceStatus}> $records */
        $records = AttendanceRecord::query()
            ->where('class_id', $class->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->toBase()
            ->get(['student_id', 'date', 'status'])
            ->map(fn (object $row): array => [
                'student_id' => (int) $row->student_id,
                'date' => substr((string) $row->date, 0, 10),
                'status' => AttendanceStatus::from((string) $row->status),
            ]);

        $enrollments = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeBetween($from, $to)
            ->get(['student_id', 'roll_no'])
            ->keyBy('student_id');

        $students = Student::query()
            ->whereKey($enrollments->keys()->merge($records->pluck('student_id'))->unique()->values())
            ->get(['id', 'first_name', 'last_name', 'admission_no'])
            ->sortBy(fn (Student $s): array => [
                $enrollments->get($s->id)->roll_no ?? PHP_INT_MAX,
                mb_strtolower($s->last_name.' '.$s->first_name),
            ]);

        $byStudent = $records->groupBy('student_id');
        $days = $records->pluck('date')->unique()->values();

        return [
            'days' => $days->all(),
            'students' => $students->map(function (Student $student) use ($byStudent, $enrollments): array {
                /** @var Collection<int, array{student_id: int, date: string, status: AttendanceStatus}> $own */
                $own = $byStudent->get($student->id, collect());

                return [
                    'id' => $student->id,
                    'roll_no' => $enrollments->get($student->id)?->roll_no,
                    'name' => $student->fullName(),
                    'admission_no' => $student->admission_no,
                    'statuses' => $own->mapWithKeys(fn (array $r): array => [$r['date'] => $r['status']->code()])->all(),
                    'totals' => $this->counts($own),
                    'rate' => $this->rate($own),
                ];
            })->values()->all(),
            'daily' => $records->groupBy('date')
                ->map(fn (Collection $day, string $date): array => ['date' => $date, ...$this->counts($day)])
                ->values()
                ->all(),
            'totals' => [...$this->counts($records), 'rate' => $this->rate($records)],
        ];
    }

    /**
     * @param  Collection<int, array{student_id: int, date: string, status: AttendanceStatus}>  $records
     * @return array{present: int, absent: int, late: int, excused: int}
     */
    private function counts(Collection $records): array
    {
        $counts = $records->countBy(fn (array $r): string => $r['status']->value);

        return [
            'present' => (int) $counts->get(AttendanceStatus::Present->value, 0),
            'absent' => (int) $counts->get(AttendanceStatus::Absent->value, 0),
            'late' => (int) $counts->get(AttendanceStatus::Late->value, 0),
            'excused' => (int) $counts->get(AttendanceStatus::Excused->value, 0),
        ];
    }

    /**
     * @param  Collection<int, array{student_id: int, date: string, status: AttendanceStatus}>  $records
     */
    private function rate(Collection $records): ?float
    {
        if ($records->isEmpty()) {
            return null;
        }

        $attended = $records->filter(fn (array $r): bool => $r['status']->countsAsAttended())->count();

        return round($attended / $records->count() * 100, 1);
    }
}
