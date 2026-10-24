<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => '2026/27',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-07-31',
            'is_current' => true,
        ];
    }

    /**
     * The year after the default one, not current yet (the target of a promotion).
     */
    public function next(): static
    {
        return $this->state(fn (): array => [
            'name' => '2027/28',
            'starts_on' => '2027-08-01',
            'ends_on' => '2028-07-31',
            'is_current' => false,
        ]);
    }
}
