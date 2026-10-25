<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles a Stripe event whose signature has been verified. Stripe delivers events at least
 * once and may send them twice at the same time, so the event id is claimed first, in the same
 * transaction as the changes it causes:
 * - a second delivery finds the id taken (or waits for the first to commit) and does nothing;
 * - if handling fails, the claim is rolled back too and Stripe's retry processes it again.
 */
final class HandleStripeWebhook
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public function __construct(
        private readonly RecordStripeCheckout $recordCheckout,
        private readonly RecordStripeRefund $recordRefund,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): string
    {
        $id = (string) $event['id'];
        $type = (string) $event['type'];
        /** @var array<string, mixed> $object */
        $object = data_get($event, 'data.object', []);

        return DB::transaction(function () use ($id, $type, $object): string {
            $claimed = StripeWebhookEvent::query()->insertOrIgnore([
                'stripe_event_id' => $id,
                'type' => $type,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($claimed === 0) {
                return self::DUPLICATE;
            }

            match ($type) {
                'checkout.session.completed' => $this->recordCheckout->handle($object),
                'charge.refunded' => $this->recordRefund->handle($object),
                // The invoice stays open and the parent can try again; Checkout already showed
                // them the error. Logged for the school office.
                'payment_intent.payment_failed' => Log::warning('Stripe payment failed', [
                    'school_id' => data_get($object, 'metadata.school_id'),
                    'invoice_id' => data_get($object, 'metadata.invoice_id'),
                    'reason' => data_get($object, 'last_payment_error.code'),
                ]),
                default => null,
            };

            return self::PROCESSED;
        });
    }
}
