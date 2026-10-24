<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * School is taken from the class; subject and term are created in the same school and year
 * unless given, so `->for($class, 'schoolClass')->for($subject)->for($term)` is the usual form.
 *
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'school_id' => fn (array $attributes): int => $this->schoolClass($attributes)->school_id,
            'subject_id' => fn (array $attributes): int => Subject::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => $this->schoolClass($attributes)->academic_year_id,
            ])->id,
            'name' => 'Test '.fake()->numberBetween(1, 9),
            'type' => AssessmentType::Test,
            'max_marks' => 20,
            'weight' => '1.00',
            'held_on' => '2026-10-01',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function schoolClass(array $attributes): SchoolClass
    {
        return SchoolClass::query()->withoutGlobalScopes()->findOrFail((int) $attributes['class_id']);
    }
}
