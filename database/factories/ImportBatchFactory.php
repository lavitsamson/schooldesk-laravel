<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\ImportBatch;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'type' => ImportType::Students,
            'original_name' => 'students.csv',
            'status' => ImportStatus::Completed,
            'total_rows' => 3,
            'processed_rows' => 3,
            'failed_rows' => 1,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }

    /**
     * Completed with an error report on the private disk.
     */
    public function withErrorReport(): static
    {
        return $this->state(fn (): array => [
            'error_report_path' => 'imports/'.fake()->uuid().'-errors.csv',
        ]);
    }
}
