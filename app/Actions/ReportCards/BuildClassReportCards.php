<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\ReportCardBatch;
use App\Models\SchoolClass;
use App\Models\Term;

/**
 * The class page: everyone in the class during the term (roll order) with their report card,
 * if generated, and the latest "generate" run with its progress.
 */
final class BuildClassReportCards
{
    /**
     * @return array{
     *     students: array<int, array{id: int, name: string, roll_no: int|null, card: array{id: int, status: string, overall_grade: string|null, has_comment: bool, comment_source: string|null, calculated_at: string}|null}>,
     *     batch: array{id: int, total: int, processed: int, failed: int, progress: int, is_finished: bool, started_at: string}|null,
     * }
     */
    public function handle(SchoolClass $class, Term $term): array
    {
        $enrollments = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->with('student:id,first_name,last_name')
            ->get()
            ->sortBy(fn (Enrollment $e): array => [$e->roll_no ?? PHP_INT_MAX, mb_strtolower($e->student->last_name.' '.$e->student->first_name)])
            ->unique('student_id')
            ->values();

        $cards = ReportCard::query()
            ->where('term_id', $term->id)
            ->whereIn('student_id', $enrollments->pluck('student_id')->all())
            ->get(['id', 'student_id', 'status', 'data', 'teacher_comment', 'comment_source'])
            ->keyBy('student_id');

        $batch = ReportCardBatch::query()
            ->where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->latest('id')
            ->first();

        return [
            'students' => $enrollments->map(function (Enrollment $enrollment) use ($cards): array {
                /** @var ReportCard|null $card */
                $card = $cards->get($enrollment->student_id);

                return [
                    'id' => $enrollment->student_id,
                    'name' => $enrollment->student->fullName(),
                    'roll_no' => $enrollment->roll_no,
                    'card' => $card === null ? null : [
                        'id' => $card->id,
                        'status' => $card->status->value,
                        'overall_grade' => $card->data['overall']['grade'],
                        'has_comment' => $card->teacher_comment !== null,
                        'comment_source' => $card->comment_source?->value,
                        'calculated_at' => $card->data['calculated_at'],
                    ],
                ];
            })->all(),
            'batch' => $batch?->summary(),
        ];
    }
}
