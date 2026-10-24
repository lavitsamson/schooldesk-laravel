<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'school_id' => fn (array $attributes): int => AcademicYear::query()
                ->withoutGlobalScopes()
                ->whereKey($attributes['academic_year_id'])
                ->value('school_id'),
            'name' => 'Term '.fake()->numberBetween(1, 3),
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-18',
        ];
    }
}
