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
            // Faker's phoneNumber() can return "555.123.4567 x890", which the phone rule rejects.
            'phone' => fake()->numerify('+49 15# #######'),
            'relationship' => fake()->randomElement([GuardianRelationship::Mother, GuardianRelationship::Father]),
        ];
    }
}
