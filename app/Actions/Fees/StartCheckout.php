<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\Invoice;
use App\Models\User;
use App\Services\StripeService;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

/**
 * A Stripe Checkout session for the invoice's open balance. Nothing is recorded here: the
 * payment exists once Stripe confirms it through the webhook (RecordStripeCheckout), never
 * because the browser came back to the success page.
 *
 * Stripe gets the invoice number, term and amount, but not the student's name: what it needs
 * to take the payment, and no more.
 */
final class StartCheckout
{
    /**
     * Stripe's minimum session lifetime: an abandoned session expires after half an hour.
     */
    private const EXPIRES_AFTER_MINUTES = 30;

    public function __construct(
        private readonly StripeService $stripe,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @return string the Checkout URL to send the parent to
     */
    public function handle(Invoice $invoice, User $payer): string
    {
        if (! $this->stripe->isConfigured()) {
            throw ValidationException::withMessages(['payment' => __('Online payment is not available. Please pay by bank transfer or at the school office.')]);
        }

        $balance = $invoice->balanceMoney();

        if (! $invoice->status->isOpen() || ! $balance->isPositive()) {
            throw ValidationException::withMessages(['payment' => __('This invoice has nothing left to pay.')]);
        }

        $school = $this->currentSchool->get();
        $invoice->loadMissing('term');
        $metadata = [
            'school_id' => (string) $school->id,
            'invoice_id' => (string) $invoice->id,
            'user_id' => (string) $payer->id,
        ];

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            // The payer's language; Stripe Checkout has no Hindi, so it picks one from the browser.
            'locale' => App::getLocale() === 'hi' ? 'auto' : App::getLocale(),
            'customer_email' => $payer->email,
            'client_reference_id' => (string) $invoice->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($balance->currency),
                    'unit_amount' => $balance->minor,
                    'product_data' => [
                        'name' => "{$school->name}: invoice {$invoice->number}",
                        'description' => $invoice->term->name,
                    ],
                ],
            ]],
            'metadata' => $metadata,
            // Copied to the payment intent (and its charge), for failures and refunds.
            'payment_intent_data' => ['metadata' => $metadata],
            'expires_at' => now()->addMinutes(self::EXPIRES_AFTER_MINUTES)->getTimestamp(),
            'success_url' => route('invoices.show', ['invoice' => $invoice, 'checkout' => 'success']),
            'cancel_url' => route('invoices.show', ['invoice' => $invoice, 'checkout' => 'cancelled']),
        ]);

        return $session['url'];
    }
}
