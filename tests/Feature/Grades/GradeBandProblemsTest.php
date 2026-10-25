<?php

declare(strict_types=1);

use App\Enums\GradeScalePreset;
use App\Support\Grading\GradeBands;
use Illuminate\Support\Facades\App;

// A feature test, not a unit test: the problems are translated messages.

$band = fn (string $label, string $min, string $max): array => ['label' => $label, 'min_percent' => $min, 'max_percent' => $max];

it('accepts the presets and rejects scales with gaps, overlaps or missing ends', function () use ($band): void {
    foreach (GradeScalePreset::cases() as $preset) {
        expect(GradeBands::problems($preset->bands()))->toBe([]);
    }

    expect(GradeBands::problems([$band('Pass', '50.00', '100.00'), $band('Fail', '0.00', '49.99')]))->toBe([])
        ->and(GradeBands::problems([$band('Pass', '50.00', '100.00'), $band('Fail', '0.00', '48.99')]))
        ->toBe(['Fail must end 0.01 below where Pass starts (50.00 %).'])
        ->and(GradeBands::problems([$band('Pass', '50.00', '100.00'), $band('Fail', '0.00', '50.00')]))
        ->toBe(['Fail must end 0.01 below where Pass starts (50.00 %).'])
        ->and(GradeBands::problems([$band('Pass', '50.00', '99.00'), $band('Fail', '1.00', '49.99')]))
        ->toBe(['The best band must go up to 100 %.', 'The lowest band must start at 0 %.'])
        ->and(GradeBands::problems([$band('Odd', '60.00', '40.00'), $band('Pass', '0.00', '59.99')]))
        ->toContain('Odd: the lowest percentage is above the highest.');
});

it('explains the problems in the active language', function () use ($band): void {
    App::setLocale('de');

    expect(GradeBands::problems([$band('Pass', '50.00', '99.00'), $band('Fail', '1.00', '49.99')]))
        ->toBe(['Der beste Bereich muss bis 100 % reichen.', 'Der niedrigste Bereich muss bei 0 % beginnen.']);
});
