<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The school is taken from the assessment, so `->for($assessment)->for($student)` is enough.
 *
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'school_id' => fn (array $attributes): int => Assessment::query()->withoutGlobalScopes()
                ->findOrFail((int) $attributes['assessment_id'])->school_id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'marks' => '15.00',
        ];
    }
}
