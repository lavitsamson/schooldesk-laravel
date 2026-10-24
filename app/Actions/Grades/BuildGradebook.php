<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Grading\GradeBands;
use App\Support\Grading\ReportCardCalculator;
use Illuminate\Support\Collection;

/**
 * A class-subject's term at a glance: its assessments as columns, the students in the class
 * during the term as rows with their marks, and each student's subject result so far.
 */
final class BuildGradebook
{
    /**
     * @return array{
     *     assessments: array<int, array{id: int, name: string, type: string, type_label: string, held_on: string, max_marks: int, weight: string, graded_count: int}>,
     *     students: array<int, array{id: int, roll_no: int|null, name: string, marks: array<int, string|null>, percentage: string|null, grade: string|null}>,
     *     average: array{percentage: string|null, grade: string|null},
     * }
     */
    public function handle(SchoolClass $class, Subject $subject, Term $term): array
    {
        $assessments = Assessment::query()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->withCount(['grades as graded_count' => fn ($q) => $q->whereNotNull('marks')])
            ->orderBy('held_on')
            ->orderBy('id')
            ->get();

        $grades = Grade::query()->whereIn('assessment_id', $assessments->modelKeys())->get(['assessment_id', 'student_id', 'marks']);
        $byStudent = $grades->groupBy('student_id');

        $enrollments = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->get(['student_id', 'roll_no'])
            ->keyBy('student_id');

        // Students who have since left still show with their marks.
        $students = Student::query()
            ->whereKey($enrollments->keys()->merge($grades->pluck('student_id'))->unique()->values()->all())
            ->get(['id', 'first_name', 'last_name'])
            ->sortBy(fn (Student $s): array => [
                $enrollments->get($s->id)->roll_no ?? PHP_INT_MAX,
                mb_strtolower($s->last_name.' '.$s->first_name),
            ]);

        $calculator = new ReportCardCalculator(GradeBands::current());

        $rows = $students->map(function (Student $student) use ($assessments, $byStudent, $enrollments, $calculator): array {
            /** @var Collection<int, Grade> $own */
            $own = $byStudent->get($student->id, collect())->keyBy('assessment_id');
            $result = $calculator->subject($assessments->map(fn (Assessment $a): array => [
                'marks' => $own->get($a->id)?->marks,
                'max_marks' => $a->max_marks,
                'weight' => $a->weight,
            ])->all());

            return [
                'id' => $student->id,
                'roll_no' => $enrollments->get($student->id)?->roll_no,
                'name' => $student->fullName(),
                'marks' => $assessments->mapWithKeys(fn (Assessment $a): array => [$a->id => $own->get($a->id)?->marks])->all(),
                'percentage' => $result['percentage'],
                'grade' => $result['grade'],
            ];
        })->values();

        return [
            'assessments' => $assessments->map(fn (Assessment $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'held_on' => $a->held_on->toDateString(),
                'max_marks' => $a->max_marks,
                'weight' => $a->weight,
                'graded_count' => (int) $a->graded_count,
            ])->values()->all(),
            'students' => $rows->all(),
            'average' => $calculator->overall($rows->pluck('percentage')->all()),
        ];
    }
}
