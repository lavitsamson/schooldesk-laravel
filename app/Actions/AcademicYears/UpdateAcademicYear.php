<?php

declare(strict_types=1);

namespace App\Actions\AcademicYears;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Updates a year and its terms. Terms are matched by id, so existing terms keep their identity
 * (assessments and invoices will point at them); terms left out are removed.
 */
final class UpdateAcademicYear
{
    /**
     * @param  array{name: string, starts_on: string, ends_on: string, terms: list<array{id?: int|null, name: string, starts_on: string, ends_on: string}>}  $data
     */
    public function handle(AcademicYear $year, array $data): AcademicYear
    {
        return DB::transaction(function () use ($year, $data): AcademicYear {
            $year->update([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ]);

            $keep = [];

            foreach ($data['terms'] as $input) {
                $attributes = ['name' => $input['name'], 'starts_on' => $input['starts_on'], 'ends_on' => $input['ends_on']];
                $term = isset($input['id'])
                    ? Term::query()->where('academic_year_id', $year->id)->findOrFail($input['id'])
                    : new Term(['academic_year_id' => $year->id]);

                $term->fill($attributes)->save();
                $keep[] = $term->id;
            }

            Term::query()->where('academic_year_id', $year->id)->whereNotIn('id', $keep)->delete();

            return $year;
        });
    }
}
