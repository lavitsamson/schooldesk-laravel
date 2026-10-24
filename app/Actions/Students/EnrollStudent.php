<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Puts a student into a class for that class's academic year. A student has at most one
 * enrolment per year, so enrolling again in the same year moves them to the new class
 * (with the next free roll number there).
 */
final class EnrollStudent
{
    public function handle(Student $student, SchoolClass $class, ?CarbonImmutable $on = null): Enrollment
    {
        return DB::transaction(function () use ($student, $class, $on): Enrollment {
            $enrollment = Enrollment::query()->firstOrNew([
                'student_id' => $student->id,
                'academic_year_id' => $class->academic_year_id,
            ]);

            if ($enrollment->exists && $enrollment->class_id === $class->id) {
                return $enrollment;
            }

            $enrollment->fill([
                'class_id' => $class->id,
                'roll_no' => (int) Enrollment::query()->where('class_id', $class->id)->max('roll_no') + 1,
            ]);

            if (! $enrollment->exists) {
                $enrollment->enrolled_on = $on ?? $this->defaultDate($class);
            }

            $enrollment->save();

            return $enrollment;
        });
    }

    /**
     * Today, kept within the academic year (enrolling into next year starts on its first day).
     */
    private function defaultDate(SchoolClass $class): CarbonImmutable
    {
        $year = AcademicYear::query()->findOrFail($class->academic_year_id);
        $today = CarbonImmutable::today();

        return match (true) {
            $today->lt($year->starts_on) => $year->starts_on,
            $today->gt($year->ends_on) => $year->ends_on,
            default => $today,
        };
    }
}
