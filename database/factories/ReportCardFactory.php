<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * School is taken from the class; student and term are created in the same school and year
 * unless given. The snapshot is a minimal one without subjects: tests that need results
 * generate the card through SaveReportCardSnapshot instead.
 *
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'school_id' => fn (array $attributes): int => $this->schoolClass($attributes)->school_id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => $this->schoolClass($attributes)->academic_year_id,
            ])->id,
            'data' => fn (array $attributes): array => [
                'student' => ['id' => $attributes['student_id'], 'first_name' => 'Test', 'last_name' => 'Student', 'admission_no' => 'T-1'],
                'class' => ['id' => $attributes['class_id'], 'name' => 'Grade 7 A'],
                'term' => ['id' => $attributes['term_id'], 'name' => 'Autumn term', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18'],
                'subjects' => [],
                'overall' => ['percentage' => null, 'grade' => null],
                'attendance' => ['days' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'rate' => null],
                'grade_scale' => [],
                'calculated_at' => now()->toIso8601String(),
            ],
            'status' => ReportCardStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => ReportCardStatus::Approved, 'approved_at' => now(), 'teacher_comment' => 'A good term.']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function schoolClass(array $attributes): SchoolClass
    {
        return SchoolClass::query()->withoutGlobalScopes()->findOrFail((int) $attributes['class_id']);
    }
}
