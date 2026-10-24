<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Enums\AttendanceStatus;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\Grading\GradeBands;
use App\Support\Grading\ReportCardCalculator;
use Illuminate\Support\Collection;

/**
 * A student's term result as a self-contained snapshot, the data of a report card: per
 * subject the weighted percentage and grade (ReportCardCalculator), the overall result,
 * attendance in the term, and the grade scale used. Report cards store this snapshot, so
 * later changes to marks or to the scale do not change a published report card.
 *
 * Subjects are those taught in the student's class plus any the student has marks in, so
 * marks from a class the student left during the term still count.
 *
 * @phpstan-type SubjectResult array{
 *     id: int, name: string, code: string,
 *     percentage: string|null, grade: string|null, graded: int, assessments_count: int,
 *     assessments: array<int, array{id: int, name: string, type: string, held_on: string, max_marks: int, weight: string, marks: string|null, remarks: string|null}>,
 * }
 * @phpstan-type Snapshot array{
 *     student: array{id: int, first_name: string, last_name: string, admission_no: string},
 *     class: array{id: int, name: string}|null,
 *     term: array{id: int, name: string, starts_on: string, ends_on: string},
 *     subjects: array<int, SubjectResult>,
 *     overall: array{percentage: string|null, grade: string|null},
 *     attendance: array{days: int, present: int, absent: int, late: int, excused: int, rate: float|null},
 *     grade_scale: list<array{label: string, min_percent: string, max_percent: string}>,
 *     calculated_at: string,
 * }
 */
final class CalculateReportCard
{
    /**
     * @return Snapshot
     */
    public function handle(Student $student, Term $term): array
    {
        $class = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $term->academic_year_id)
            ->with('schoolClass')
            ->first()
            ?->schoolClass;

        $snapshots = $this->calculate($term, collect([$student]), collect([$student->id => $class]));

        /** @var Snapshot */
        return $snapshots[$student->id];
    }

    /**
     * Snapshots for everyone in the class during the term, in roll order, keyed by student id.
     * A handful of queries for the whole class, not per student.
     *
     * @return array<int, Snapshot>
     */
    public function forClass(SchoolClass $class, Term $term): array
    {
        $students = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->with('student')
            ->get()
            ->sortBy(fn (Enrollment $e): array => [$e->roll_no ?? PHP_INT_MAX, mb_strtolower($e->student->last_name.' '.$e->student->first_name)])
            ->map(fn (Enrollment $e): Student => $e->student)
            ->values();

        /** @var Collection<int, SchoolClass|null> $classes */
        $classes = collect($students->all())->mapWithKeys(fn (Student $s): array => [$s->id => $class]);

        return $this->calculate($term, $students, $classes);
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, SchoolClass|null>  $classes  student id => class in the term's year
     * @return array<int, Snapshot>
     */
    private function calculate(Term $term, Collection $students, Collection $classes): array
    {
        $studentIds = $students->map(fn (Student $s): int => $s->id)->all();
        $classIds = $classes->filter()->map(fn (SchoolClass $c): int => $c->id)->unique()->values()->all();

        $grades = Grade::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('assessment', fn ($q) => $q->where('term_id', $term->id))
            ->get(['assessment_id', 'student_id', 'marks', 'remarks']);

        $assessments = Assessment::query()
            ->where('term_id', $term->id)
            ->where(fn ($q) => $q->whereIn('class_id', $classIds)->orWhereIn('id', $grades->pluck('assessment_id')->unique()->all()))
            ->orderBy('held_on')
            ->orderBy('id')
            ->get();

        $subjects = Subject::query()
            ->whereIn('id', $assessments->pluck('subject_id')
                ->merge(TeachingAssignment::query()->whereIn('class_id', $classIds)->pluck('subject_id'))
                ->unique()
                ->all())
            ->orderBy('name')
            ->get();

        $taught = TeachingAssignment::query()->whereIn('class_id', $classIds)->get(['class_id', 'subject_id'])->groupBy('class_id');
        $gradesByStudent = $grades->groupBy('student_id');
        $attendance = $this->attendance($term, $studentIds);
        $bands = GradeBands::current();
        $calculator = new ReportCardCalculator($bands);
        $calculatedAt = now()->toIso8601String();

        $snapshots = [];

        foreach ($students as $student) {
            $class = $classes->get($student->id);
            /** @var Collection<int, Grade> $own */
            $own = $gradesByStudent->get($student->id, collect())->keyBy('assessment_id');
            $taughtHere = $class === null ? [] : $taught->get($class->id, collect())->pluck('subject_id')->all();

            $relevant = $assessments->filter(fn (Assessment $a): bool => $a->class_id === $class?->id || $own->has($a->id));
            $bySubject = $relevant->groupBy('subject_id');

            $results = $subjects
                ->filter(fn (Subject $s): bool => $bySubject->has($s->id) || in_array($s->id, $taughtHere, true))
                ->map(function (Subject $subject) use ($bySubject, $own, $calculator): array {
                    /** @var Collection<int, Assessment> $list */
                    $list = $bySubject->get($subject->id, collect());
                    $details = $list->map(fn (Assessment $a): array => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'type' => $a->type->value,
                        'held_on' => $a->held_on->toDateString(),
                        'max_marks' => $a->max_marks,
                        'weight' => $a->weight,
                        'marks' => $own->get($a->id)?->marks,
                        'remarks' => $own->get($a->id)?->remarks,
                    ])->values();

                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        ...$calculator->subject($details->all()),
                        'assessments_count' => $details->count(),
                        'assessments' => $details->all(),
                    ];
                })
                ->values();

            $snapshots[$student->id] = [
                'student' => [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'admission_no' => $student->admission_no,
                ],
                'class' => $class === null ? null : ['id' => $class->id, 'name' => $class->displayName()],
                'term' => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'starts_on' => $term->starts_on->toDateString(),
                    'ends_on' => $term->ends_on->toDateString(),
                ],
                'subjects' => $results->all(),
                'overall' => $calculator->overall($results->pluck('percentage')->all()),
                'attendance' => $attendance[$student->id] ?? $this->attendanceSummary([]),
                'grade_scale' => $bands->all(),
                'calculated_at' => $calculatedAt,
            ];
        }

        return $snapshots;
    }

    /**
     * Attendance per student in the term, counted in the database.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, array{days: int, present: int, absent: int, late: int, excused: int, rate: float|null}>
     */
    private function attendance(Term $term, array $studentIds): array
    {
        return AttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('date', [$term->starts_on->toDateString(), $term->ends_on->toDateString()])
            ->toBase()
            ->selectRaw('student_id, status, count(*) as aggregate')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows): array => $this->attendanceSummary(
                $rows->mapWithKeys(fn (object $row): array => [(string) $row->status => (int) $row->aggregate])->all(),
            ))
            ->all();
    }

    /**
     * @param  array<string, int>  $counts  status => days
     * @return array{days: int, present: int, absent: int, late: int, excused: int, rate: float|null}
     */
    private function attendanceSummary(array $counts): array
    {
        $days = array_sum($counts);
        $attended = array_sum(array_map(
            fn (AttendanceStatus $status): int => $status->countsAsAttended() ? ($counts[$status->value] ?? 0) : 0,
            AttendanceStatus::cases(),
        ));

        return [
            'days' => $days,
            'present' => $counts[AttendanceStatus::Present->value] ?? 0,
            'absent' => $counts[AttendanceStatus::Absent->value] ?? 0,
            'late' => $counts[AttendanceStatus::Late->value] ?? 0,
            'excused' => $counts[AttendanceStatus::Excused->value] ?? 0,
            'rate' => $days === 0 ? null : round($attended / $days * 100, 1),
        ];
    }
}
