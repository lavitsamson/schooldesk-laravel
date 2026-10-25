<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Actions\Grades\CalculateReportCard;
use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Creates a student's report card for the term, or recalculates its snapshot while it is a
 * draft. An approved or published card is left as it is: its snapshot is what was signed off.
 * The teacher's comment survives a recalculation.
 */
final class SaveReportCardSnapshot
{
    public function __construct(private readonly CalculateReportCard $calculate) {}

    public function handle(Student $student, Term $term, SchoolClass $class): ReportCard
    {
        return DB::transaction(function () use ($student, $term, $class): ReportCard {
            $card = ReportCard::query()
                ->where('student_id', $student->id)
                ->where('term_id', $term->id)
                ->lockForUpdate()
                ->first();

            if ($card !== null && ! $card->isDraft()) {
                return $card;
            }

            $card ??= new ReportCard([
                'student_id' => $student->id,
                'term_id' => $term->id,
                'status' => ReportCardStatus::Draft,
            ]);

            $card->fill([
                'class_id' => $class->id,
                'data' => $this->calculate->handle($student, $term),
            ])->save();

            return $card;
        });
    }
}
