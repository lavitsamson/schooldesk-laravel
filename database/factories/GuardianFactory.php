<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GuardianRelationship;
use App\Models\Guardian;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'relationship' => fake()->randomElement([GuardianRelationship::Mother, GuardianRelationship::Father]),
        ];
    }
}
