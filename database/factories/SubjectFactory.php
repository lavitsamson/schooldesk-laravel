<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'school_id' => School::factory(),
            'name' => Str::ucfirst($name),
            'code' => Str::upper(Str::substr($name, 0, 4)).fake()->unique()->numberBetween(10, 99),
        ];
    }
}
