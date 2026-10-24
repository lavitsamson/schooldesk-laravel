<?php

declare(strict_types=1);

namespace App\Actions\AcademicYears;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * Adds an academic year with its terms, typically next year's, ready for promotion.
 * Optionally copies the classes (name, section, class teacher) of another year.
 */
final class CreateAcademicYear
{
    /**
     * @param  array{name: string, starts_on: string, ends_on: string, terms: list<array{name: string, starts_on: string, ends_on: string}>}  $data
     */
    public function handle(array $data, ?AcademicYear $copyClassesFrom = null): AcademicYear
    {
        return DB::transaction(function () use ($data, $copyClassesFrom): AcademicYear {
            $year = AcademicYear::query()->create([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                // A school without a current year (should not happen after setup) gets this one.
                'is_current' => ! AcademicYear::query()->current()->exists(),
            ]);

            foreach ($data['terms'] as $term) {
                $year->terms()->create($term);
            }

            if ($copyClassesFrom !== null) {
                SchoolClass::query()
                    ->where('academic_year_id', $copyClassesFrom->id)
                    ->orderBy('name')
                    ->orderBy('section')
                    ->get()
                    ->each(fn (SchoolClass $class) => $year->classes()->create([
                        'name' => $class->name,
                        'section' => $class->section,
                        'class_teacher_id' => $class->class_teacher_id,
                    ]));
            }

            return $year;
        });
    }
}
