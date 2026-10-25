<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Jobs\GeneratePaymentReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Log;

/**
 * Records the payment of a completed Checkout session (checkout.session.completed). The school
 * and invoice come from the session's metadata, which StartCheckout set and Stripe's signature
 * vouches for; the invoice is then looked up inside that school only.
 *
 * Stripe has taken the money, so the payment is recorded whatever the invoice's state: also
 * when the parent paid in two tabs, or the office recorded cash in the meantime. The invoice
 * then shows as paid with a credit, and the office refunds the difference in Stripe
 * (charge.refunded brings that back here).
 */
final class RecordStripeCheckout
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly RecalculateInvoiceStatus $recalculate,
    ) {}

    /**
     * @param  array<string, mixed>  $session  the Checkout Session object
     */
    public function handle(array $session): void
    {
        // Delayed payment methods complete later (async_payment_succeeded); cards are paid here.
        if (data_get($session, 'payment_status') !== 'paid') {
            return;
        }

        $schoolId = (int) data_get($session, 'metadata.school_id');
        $invoiceId = (int) data_get($session, 'metadata.invoice_id');
        $paymentIntent = data_get($session, 'payment_intent');
        $amount = (int) data_get($session, 'amount_total');
        $school = School::query()->find($schoolId);

        if ($school === null || $invoiceId === 0 || ! is_string($paymentIntent) || $amount <= 0) {
            Log::error('Stripe checkout without a known school, invoice or payment', ['session' => data_get($session, 'id')]);

            return;
        }

        $this->currentSchool->run($school, function (School $school) use ($session, $invoiceId, $paymentIntent, $amount): void {
            $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);

            if ($invoice === null || strtoupper((string) data_get($session, 'currency')) !== strtoupper($school->currency)) {
                Log::error('Stripe checkout for an unknown invoice or in another currency', [
                    'session' => data_get($session, 'id'),
                    'school_id' => $school->id,
                    'invoice_id' => $invoiceId,
                ]);

                return;
            }

            if (Payment::query()->where('stripe_payment_intent_id', $paymentIntent)->exists()) {
                return;
            }

            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'method' => PaymentMethod::Stripe,
                'stripe_payment_intent_id' => $paymentIntent,
                'paid_at' => now(),
            ]);

            $this->recalculate->handle($invoice);

            GeneratePaymentReceipt::dispatch($school->id, $payment->id)->afterCommit();
        });
    }
}
