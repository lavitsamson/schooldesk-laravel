<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Stripe event that has been handled. Stripe delivers events at least once, so the unique
 * event id is what makes the webhook idempotent. Platform-wide, not school-owned.
 *
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property Carbon $processed_at
 */
#[Fillable(['stripe_event_id', 'type', 'processed_at'])]
class StripeWebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
