<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' School';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'country' => 'DE',
            'timezone' => 'Europe/Berlin',
            'locale' => Locale::English,
            'currency' => 'EUR',
        ];
    }

    public function completedSetup(): static
    {
        return $this->state(fn (): array => ['setup_completed_at' => now()]);
    }
}
