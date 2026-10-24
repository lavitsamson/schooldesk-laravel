<?php

declare(strict_types=1);

namespace App\Actions\GradeScales;

use App\Models\GradeScale;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the current school's grade scale with the given bands (already checked to cover
 * 0–100 % without gaps). Results are worked out on the fly, so they follow the new scale;
 * report cards keep the scale they were calculated with in their snapshot.
 */
final class SaveGradeScale
{
    /**
     * @param  list<array{label: string, min_percent: string, max_percent: string}>  $bands
     */
    public function handle(array $bands): void
    {
        DB::transaction(function () use ($bands): void {
            GradeScale::query()->delete();

            foreach ($bands as $band) {
                GradeScale::query()->create($band);
            }
        });
    }
}
