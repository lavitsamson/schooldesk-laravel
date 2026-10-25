<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A cash payment of 500.00. Does not change the invoice's status: tests that need that record
 * payments through RecordPayment.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'school_id' => fn (array $attributes): int => Invoice::query()->withoutGlobalScopes()->whereKey($attributes['invoice_id'])->value('school_id'),
            'amount' => 50_000,
            'method' => PaymentMethod::Cash,
            'paid_at' => now(),
        ];
    }
}
