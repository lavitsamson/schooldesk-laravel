<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The current year's classes a user can take attendance for (all of them for school admins,
 * their own for teachers), with how far attendance has been taken on the date.
 */
final class ListAttendanceClasses
{
    /**
     * @return array<int, array{id: int, name: string, students_count: int, recorded_count: int, is_class_teacher: bool}>
     */
    public function handle(User $user, CarbonImmutable $date): array
    {
        return SchoolClass::query()
            ->whereIn('academic_year_id', AcademicYear::query()->current()->select('id'))
            ->when(! $user->can('viewAllClasses', AttendanceRecord::class), fn (Builder $q) => $q->taughtBy($user))
            ->withCount([
                'enrollments as students_count' => fn ($q) => $q->activeOn($date),
                'attendanceRecords as recorded_count' => fn ($q) => $q->where('date', $date->toDateString()),
            ])
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(fn (SchoolClass $class): array => [
                'id' => $class->id,
                'name' => $class->displayName(),
                'students_count' => (int) $class->students_count,
                'recorded_count' => (int) $class->recorded_count,
                'is_class_teacher' => $class->class_teacher_id === $user->id,
            ])
            ->values()
            ->all();
    }
}
