<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
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
            'name' => 'Grade '.fake()->numberBetween(1, 12),
            'section' => fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
