<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the current academic year and replaces its terms.
 * Only used while the school is being set up, before anything depends on the terms.
 */
final class SaveAcademicYear
{
    /**
     * @param  array{name: string, starts_on: string, ends_on: string, terms: list<array{name: string, starts_on: string, ends_on: string}>}  $data
     */
    public function handle(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data): AcademicYear {
            $year = AcademicYear::query()->where('is_current', true)->first()
                ?? new AcademicYear(['is_current' => true]);

            $year->fill([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ])->save();

            Term::query()->where('academic_year_id', $year->id)->delete();

            foreach ($data['terms'] as $term) {
                $year->terms()->create($term);
            }

            return $year;
        });
    }
}
