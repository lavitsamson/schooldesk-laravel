<?php

declare(strict_types=1);

use App\Enums\GradeScalePreset;
use App\Support\Grading\GradeBands;
use App\Support\Grading\ReportCardCalculator;

function calculator(GradeScalePreset $preset = GradeScalePreset::LetterAToF): ReportCardCalculator
{
    return new ReportCardCalculator(new GradeBands($preset->bands()));
}

/**
 * @return array{marks: string|null, max_marks: int, weight: string}
 */
function marked(?string $marks, int $max, string $weight = '1.00'): array
{
    return ['marks' => $marks, 'max_marks' => $max, 'weight' => $weight];
}

it('weights each assessment by its share of the marks', function (): void {
    // 15/20 = 75 % at weight 1, 45/50 = 90 % at weight 2: (75 + 2 × 90) / 3 = 85 %.
    expect(calculator()->subject([marked('15', 20), marked('45', 50, '2')]))
        ->toBe(['percentage' => '85.00', 'grade' => 'B', 'graded' => 2]);
});

it('does not let max marks act as a weight', function (): void {
    // 10/10 and 50/100 at equal weights are 75 %, not 60/110 = 54.55 %.
    expect(calculator()->subject([marked('10', 10), marked('50', 100)])['percentage'])->toBe('75.00');
});

it('leaves assessments without marks out instead of counting them as zero', function (): void {
    expect(calculator()->subject([marked('18', 20), marked(null, 20), marked(null, 100, '2')]))
        ->toBe(['percentage' => '90.00', 'grade' => 'A', 'graded' => 1]);
});

it('has no result without any marks', function (): void {
    expect(calculator()->subject([marked(null, 20)]))->toBe(['percentage' => null, 'grade' => null, 'graded' => 0])
        ->and(calculator()->subject([]))->toBe(['percentage' => null, 'grade' => null, 'graded' => 0]);
});

it('rounds half up to two decimals, exactly', function (array $assessments, string $expected): void {
    expect(calculator()->subject($assessments)['percentage'])->toBe($expected);
})->with([
    // 2/3 = 66.666… %
    'repeating decimal' => [[marked('2', 3)], '66.67'],
    // 1/8 = 12.5 %, 17.5/20 = 87.5 %
    'exact half marks' => [[marked('17.5', 20)], '87.50'],
    // (89.99 + 90.00) / 2 = 89.995: half up, never down through float error
    'exactly half a hundredth' => [[marked('89.99', 100), marked('90', 100)], '90.00'],
    // 0.5/3 = 16.6666… %, 1/3 = 33.333… %
    'thirds' => [[marked('0.5', 3), marked('1', 3)], '25.00'],
    'decimal weights' => [[marked('10', 20, '0.5'), marked('20', 20, '1.5')], '87.50'],
]);

it('averages the subject percentages as shown, with equal weight per subject', function (): void {
    expect(calculator()->overall(['80.00', '91.00', null, '75.50']))
        ->toBe(['percentage' => '82.17', 'grade' => 'B'])
        ->and(calculator()->overall([null, null]))->toBe(['percentage' => null, 'grade' => null]);
});

it('finds the letter grade at the band boundaries', function (string $percentage, string $grade): void {
    expect((new GradeBands(GradeScalePreset::LetterAToF->bands()))->labelFor($percentage))->toBe($grade);
})->with([
    ['100.00', 'A'], ['90.00', 'A'], ['89.99', 'B'], ['80.00', 'B'], ['79.99', 'C'],
    ['60.00', 'D'], ['50.00', 'E'], ['49.99', 'F'], ['0.00', 'F'],
]);

it('finds the German grade at the band boundaries, 1 being the best', function (string $percentage, string $grade): void {
    expect((new GradeBands(GradeScalePreset::German1To6->bands()))->labelFor($percentage))->toBe($grade);
})->with([
    ['100.00', '1'], ['92.00', '1'], ['91.99', '2'], ['81.00', '2'], ['80.99', '3'],
    ['67.00', '3'], ['66.99', '4'], ['50.00', '4'], ['49.99', '5'], ['30.00', '5'], ['29.99', '6'], ['0.00', '6'],
]);

it('grades a calculated result that rounds onto a boundary', function (): void {
    // 91.995 % rounds to 92.00: a 1, not a 2.
    $result = calculator(GradeScalePreset::German1To6)->subject([marked('91.99', 100), marked('92', 100)]);

    expect($result)->toBe(['percentage' => '92.00', 'grade' => '1', 'graded' => 2]);
});
