<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use App\Models\Student;
use App\Models\StudentExport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<StudentExport>
 */
class StudentExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => fn (array $attributes) => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
        ];
    }

    /**
     * Built, with its ZIP on the private disk until the link expires.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
            'expires_at' => now()->addHours((int) config('schooldesk.gdpr.export_link_hours')),
        ])->afterCreating(function (StudentExport $export): void {
            $export->forceFill(['file_path' => $export->filePath()])->save();
            Storage::disk(StudentExport::DISK)->put($export->filePath(), 'zip');
        });
    }
}
