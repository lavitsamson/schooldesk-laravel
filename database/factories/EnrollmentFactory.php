<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * School and academic year are taken from the class, so `->for($class, 'schoolClass')` is enough.
 *
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'school_id' => fn (array $attributes): int => $this->schoolClass($attributes)->school_id,
            'academic_year_id' => fn (array $attributes): int => $this->schoolClass($attributes)->academic_year_id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'roll_no' => null,
            'enrolled_on' => fn (array $attributes): string => AcademicYear::query()->withoutGlobalScopes()
                ->findOrFail((int) $attributes['academic_year_id'])->starts_on->toDateString(),
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
