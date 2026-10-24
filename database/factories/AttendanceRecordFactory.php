<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The school is taken from the class, so `->for($class, 'schoolClass')->for($student)` is enough.
 *
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'school_id' => fn (array $attributes): int => SchoolClass::query()->withoutGlobalScopes()
                ->findOrFail((int) $attributes['class_id'])->school_id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'date' => '2026-10-01',
            'status' => AttendanceStatus::Present,
        ];
    }

    public function absent(): static
    {
        return $this->state(['status' => AttendanceStatus::Absent]);
    }
}
