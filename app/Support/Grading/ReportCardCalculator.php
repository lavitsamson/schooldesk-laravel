<?php

declare(strict_types=1);

namespace App\Support\Grading;

/**
 * The arithmetic of term results, without the database (ADR 0006):
 *
 * - A subject's percentage is the weighted mean of its marked assessments:
 *   Σ(weight × marks / max_marks) / Σ(weight) × 100. Assessments without marks (not held
 *   yet, or the student was absent) are left out rather than counted as zero.
 * - The overall percentage is the plain mean of the subject percentages as shown (rounded),
 *   so anyone can check it from the report card. Subjects without marks are left out.
 * - Percentages are rounded half up to two decimals, the precision of the grade scale.
 *
 * All arithmetic is exact (Decimal, bcmath on strings): marks such as 17.5 / 20 are exact,
 * and a result of 89.995 rounds to 90.00 every time instead of depending on float error.
 */
final class ReportCardCalculator
{
    public function __construct(private readonly GradeBands $bands) {}

    /**
     * @param  iterable<array{marks: string|null, max_marks: int, weight: string}>  $assessments
     * @return array{percentage: string|null, grade: string|null, graded: int}
     */
    public function subject(iterable $assessments): array
    {
        $weighted = '0';
        $weights = '0';
        $graded = 0;

        foreach ($assessments as $assessment) {
            if ($assessment['marks'] === null || $assessment['max_marks'] <= 0) {
                continue;
            }

            $share = Decimal::div($assessment['marks'], (string) $assessment['max_marks']);
            $weighted = Decimal::add($weighted, Decimal::mul($assessment['weight'], $share));
            $weights = Decimal::add($weights, $assessment['weight']);
            $graded++;
        }

        $percentage = Decimal::compare($weights, '0') > 0
            ? Decimal::round(Decimal::div(Decimal::mul($weighted, '100'), $weights))
            : null;

        return ['percentage' => $percentage, 'grade' => $this->bands->labelFor($percentage), 'graded' => $graded];
    }

    /**
     * @param  iterable<string|null>  $subjectPercentages
     * @return array{percentage: string|null, grade: string|null}
     */
    public function overall(iterable $subjectPercentages): array
    {
        $sum = '0';
        $count = 0;

        foreach ($subjectPercentages as $percentage) {
            if ($percentage !== null) {
                $sum = Decimal::add($sum, $percentage);
                $count++;
            }
        }

        $percentage = $count > 0 ? Decimal::round(Decimal::div($sum, (string) $count)) : null;

        return ['percentage' => $percentage, 'grade' => $this->bands->labelFor($percentage)];
    }
}
