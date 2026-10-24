<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * The mark entry grid of an assessment: the students in the class on the day it was held,
 * plus anyone who already has marks in it (e.g. moved class since), with what was entered.
 */
final class LoadGradeSheet
{
    /**
     * @return array{
     *     students: array<int, array{id: int, roll_no: int|null, name: string, admission_no: string, marks: string|null, remarks: string|null}>,
     *     saved: array{count: int, by: string|null, at: string|null},
     * }
     */
    public function handle(Assessment $assessment): array
    {
        $grades = Grade::query()->where('assessment_id', $assessment->id)->with('enteredBy')->get()->keyBy('student_id');

        $enrollments = Enrollment::query()
            ->where('class_id', $assessment->class_id)
            ->activeOn($assessment->held_on)
            ->get(['student_id', 'roll_no'])
            ->keyBy('student_id');

        $students = self::students($enrollments, $grades->keys());
        $latest = $grades->sortByDesc('updated_at')->first();

        return [
            'students' => $students->map(function (Student $student) use ($grades, $enrollments): array {
                /** @var Grade|null $grade */
                $grade = $grades->get($student->id);

                return [
                    'id' => $student->id,
                    'roll_no' => $enrollments->get($student->id)?->roll_no,
                    'name' => $student->fullName(),
                    'admission_no' => $student->admission_no,
                    'marks' => $grade?->marks,
                    'remarks' => $grade?->remarks,
                ];
            })->values()->all(),
            'saved' => [
                'count' => $grades->count(),
                'by' => $latest?->enteredBy?->name,
                'at' => $latest?->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * The ids of the students who belong on the sheet: one query for the enrolments, one for
     * the existing marks. Used by the save request to reject anyone else.
     *
     * @return array<int, int>
     */
    public static function studentIds(Assessment $assessment): array
    {
        return Enrollment::query()
            ->where('class_id', $assessment->class_id)
            ->activeOn($assessment->held_on)
            ->pluck('student_id')
            ->merge(Grade::query()->where('assessment_id', $assessment->id)->pluck('student_id'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments  keyed by student id
     * @param  Collection<int, int>  $graded
     * @return Collection<int, Student>
     */
    private static function students(Collection $enrollments, Collection $graded): Collection
    {
        return Student::query()
            ->whereKey($enrollments->keys()->merge($graded)->unique()->values()->all())
            ->get(['id', 'first_name', 'last_name', 'admission_no'])
            ->sortBy(fn (Student $s): array => [
                $enrollments->get($s->id)->roll_no ?? PHP_INT_MAX,
                mb_strtolower($s->last_name.' '.$s->first_name),
            ])
            ->values();
    }
}
