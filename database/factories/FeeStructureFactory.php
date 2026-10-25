<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FeeFrequency;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A term fee of 1,200.00 for every class of the year.
 *
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
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
            'class_id' => null,
            'name' => 'Tuition',
            'amount' => 120_000,
            'frequency' => FeeFrequency::Term,
            'due_day_offset' => 14,
        ];
    }

    public function forClass(SchoolClass $class): static
    {
        return $this->state([
            'class_id' => $class->id,
            'academic_year_id' => $class->academic_year_id,
            'school_id' => $class->school_id,
        ]);
    }
}
