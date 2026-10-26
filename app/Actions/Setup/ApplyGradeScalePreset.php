<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Enums\GradeScalePreset;
use App\Models\GradeScale;
use App\Support\CurrentSchool;
use App\Support\Grading\GradeBands;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the current school's grade scale with one of the presets.
 */
final class ApplyGradeScalePreset
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(GradeScalePreset $preset): void
    {
        DB::transaction(function () use ($preset): void {
            GradeScale::query()->delete();

            foreach ($preset->bands() as $band) {
                GradeScale::query()->create($band);
            }

            GradeBands::forget($this->currentSchool->id());
        });
    }
}
