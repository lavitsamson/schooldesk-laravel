<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * Makes the current academic year's classes match the given list (matched on name + section).
 */
final class SyncClasses
{
    /**
     * @param  list<array{name: string, section?: string|null}>  $classes
     */
    public function handle(array $classes): void
    {
        DB::transaction(function () use ($classes): void {
            $year = AcademicYear::query()->where('is_current', true)->firstOrFail();

            $keep = [];

            foreach ($classes as $class) {
                $keep[] = SchoolClass::query()->firstOrCreate([
                    'academic_year_id' => $year->id,
                    'name' => $class['name'],
                    'section' => ($class['section'] ?? null) ?: null,
                ])->id;
            }

            SchoolClass::query()
                ->where('academic_year_id', $year->id)
                ->whereNotIn('id', $keep)
                ->delete();
        });
    }
}
