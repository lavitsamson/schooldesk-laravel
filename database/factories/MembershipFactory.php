<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'status' => MembershipStatus::Active,
            'invited_at' => null,
        ];
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipStatus::Invited,
            'invited_at' => now(),
        ]);
    }
}
