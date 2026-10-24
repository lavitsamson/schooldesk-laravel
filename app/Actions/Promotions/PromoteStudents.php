<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Actions\Students\ChangeStudentStatus;
use App\Actions\Students\EnrollStudent;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * Moves this year's students on, class by class, in one transaction:
 * - into a class of next year (a new enrolment, starting on the first day of that year), or
 * - out of the school as graduates (their enrolment this year closes on its last day).
 *
 * Excluded students (e.g. repeating a year) and students already enrolled next year are
 * skipped, so running it twice changes nothing.
 */
final class PromoteStudents
{
    public function __construct(
        private readonly EnrollStudent $enrollStudent,
        private readonly ChangeStudentStatus $changeStatus,
    ) {}

    /**
     * @param  list<array{from_class_id: int, target: int|string|null}>  $mappings
     * @param  list<int>  $excludedStudentIds
     * @return array{promoted: int, graduated: int, skipped: int}
     */
    public function handle(AcademicYear $from, AcademicYear $to, array $mappings, array $excludedStudentIds = []): array
    {
        return DB::transaction(function () use ($from, $to, $mappings, $excludedStudentIds): array {
            $result = ['promoted' => 0, 'graduated' => 0, 'skipped' => 0];
            $excluded = array_flip($excludedStudentIds);
            $alreadyEnrolled = Enrollment::query()->where('academic_year_id', $to->id)->pluck('student_id')->flip();

            foreach ($mappings as $mapping) {
                $target = $mapping['target'];
                $targetClass = is_int($target)
                    ? SchoolClass::query()->where('academic_year_id', $to->id)->findOrFail($target)
                    : null;

                $enrollments = Enrollment::query()
                    ->where('academic_year_id', $from->id)
                    ->where('class_id', $mapping['from_class_id'])
                    ->whereNull('left_on')
                    ->with('student')
                    ->get()
                    ->filter(fn (Enrollment $e): bool => $e->student->status === StudentStatus::Active)
                    // Roll numbers in the new class follow the alphabet.
                    ->sortBy(fn (Enrollment $e): string => mb_strtolower($e->student->last_name.' '.$e->student->first_name));

                foreach ($enrollments as $enrollment) {
                    $student = $enrollment->student;

                    if ($target === null || isset($excluded[$student->id]) || $alreadyEnrolled->has($student->id)) {
                        $result['skipped']++;
                    } elseif ($targetClass !== null) {
                        $this->enrollStudent->handle($student, $targetClass, $to->starts_on);
                        $result['promoted']++;
                    } else {
                        $this->changeStatus->handle($student, StudentStatus::Graduated, $from->ends_on);
                        $result['graduated']++;
                    }
                }
            }

            return $result;
        });
    }
}
