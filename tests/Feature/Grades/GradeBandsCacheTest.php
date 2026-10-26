<?php

declare(strict_types=1);

use App\Actions\GradeScales\SaveGradeScale;
use App\Actions\Setup\ApplyGradeScalePreset;
use App\Enums\GradeScalePreset;
use App\Models\GradeScale;
use App\Support\Grading\GradeBands;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    [$this->school] = setUpSchool();
    inSchool($this->school, fn () => app(ApplyGradeScalePreset::class)->handle(GradeScalePreset::LetterAToF));
});

it('reads a school\'s grade scale once and then from the cache', function (): void {
    inSchool($this->school, fn () => GradeBands::current());

    DB::enableQueryLog();
    $label = inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00'));

    expect($label)->toBe('A')
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'grade_scales')))->toBeEmpty();
});

it('drops the cached scale when the scale is saved or a preset applied', function (): void {
    expect(inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00')))->toBe('A');

    inSchool($this->school, fn () => app(ApplyGradeScalePreset::class)->handle(GradeScalePreset::German1To6));
    expect(inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00')))->toBe('1');

    inSchool($this->school, fn () => app(SaveGradeScale::class)->handle([
        ['label' => 'Pass', 'min_percent' => '50.00', 'max_percent' => '100.00'],
        ['label' => 'Fail', 'min_percent' => '0.00', 'max_percent' => '49.99'],
    ]));
    expect(inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00')))->toBe('Pass');
});

it('keeps the old scale cached when saving the new one fails', function (): void {
    inSchool($this->school, fn () => GradeBands::current());

    try {
        DB::transaction(function (): void {
            inSchool($this->school, fn () => app(ApplyGradeScalePreset::class)->handle(GradeScalePreset::German1To6));

            throw new RuntimeException('Rolled back');
        });
    } catch (RuntimeException) {
    }

    expect(inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00')))->toBe('A')
        ->and(inSchool($this->school, fn () => GradeScale::query()->where('label', 'A')->exists()))->toBeTrue();
});

it('caches each school\'s scale separately', function (): void {
    [$other] = setUpSchool();
    inSchool($other, fn () => app(ApplyGradeScalePreset::class)->handle(GradeScalePreset::German1To6));

    expect(inSchool($this->school, fn () => GradeBands::current()->labelFor('95.00')))->toBe('A')
        ->and(inSchool($other, fn () => GradeBands::current()->labelFor('95.00')))->toBe('1');
});
