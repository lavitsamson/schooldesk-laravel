<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scopes\SchoolScope;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Log;

/**
 * Applies a refund made in the Stripe dashboard (charge.refunded). The payment is found by its
 * payment intent id, which Stripe makes globally unique, so this one lookup runs without the
 * school scope; everything after it runs inside the payment's school.
 *
 * amount_refunded is the charge's running total, so replaying an older event never refunds
 * twice: the payment simply takes the latest total.
 */
final class RecordStripeRefund
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly RecalculateInvoiceStatus $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $charge  the Charge object
     */
    public function handle(array $charge): void
    {
        $paymentIntent = data_get($charge, 'payment_intent');

        $found = is_string($paymentIntent)
            ? Payment::query()->withoutGlobalScope(SchoolScope::class)->where('stripe_payment_intent_id', $paymentIntent)->first()
            : null;

        if ($found === null) {
            Log::warning('Stripe refund for an unknown payment', ['charge' => data_get($charge, 'id')]);

            return;
        }

        $refunded = (int) data_get($charge, 'amount_refunded');

        $this->currentSchool->run($found->school_id, function () use ($found, $refunded): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($found->invoice_id);
            $payment = Payment::query()->findOrFail($found->id);

            $payment->update([
                'refunded_amount' => min(max($refunded, $payment->refunded_amount), $payment->amount),
                'refunded_at' => now(),
            ]);

            $this->recalculate->handle($invoice);
        });
    }
}
