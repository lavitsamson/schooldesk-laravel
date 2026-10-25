<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Jobs\GeneratePaymentReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\Services\StripeService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
    $this->app->forgetInstance(StripeService::class);

    $this->travelTo('2026-09-10 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    generateInvoices($this->school, $this->term);
    $this->invoice = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[0]->id)->sole());

    $this->checkoutCompleted = fn (int $amount = 120_000, array $overrides = []): array => [
        'id' => 'evt_'.Str::random(12),
        'type' => 'checkout.session.completed',
        'data' => ['object' => array_replace_recursive([
            'id' => 'cs_test_'.Str::random(8),
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_'.Str::random(12),
            'amount_total' => $amount,
            'currency' => 'eur',
            'metadata' => ['school_id' => (string) $this->school->id, 'invoice_id' => (string) $this->invoice->id, 'user_id' => (string) $this->parent->id],
        ], $overrides)],
    ];
    $this->payments = fn () => inSchool($this->school, fn () => Payment::query()->orderBy('id')->get());
});

it('records a completed Checkout payment', function (): void {
    stripeWebhook(($this->checkoutCompleted)())->assertOk()->assertJson(['status' => 'processed']);

    $payment = ($this->payments)()->sole();
    expect($payment->amount)->toBe(120_000)
        ->and($payment->method)->toBe(PaymentMethod::Stripe)
        ->and($payment->stripe_payment_intent_id)->toStartWith('pi_')
        ->and($payment->recorded_by)->toBeNull()
        ->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    Queue::assertPushed(GeneratePaymentReceipt::class, fn (GeneratePaymentReceipt $job): bool => $job->paymentId === $payment->id);
});

it('rejects requests without a valid signature', function (?string $secret, ?int $timestamp): void {
    stripeWebhook(($this->checkoutCompleted)(), $secret, $timestamp)->assertStatus(400);

    expect(($this->payments)())->toBeEmpty()
        ->and(StripeWebhookEvent::query()->count())->toBe(0);
})->with([
    'signed with another secret' => ['whsec_attacker', null],
    'replayed after the tolerance' => [null, time() - 3600],
]);

it('rejects requests with no signature at all', function (): void {
    $this->postJson(route('webhooks.stripe'), ($this->checkoutCompleted)())->assertStatus(400);
});

it('handles a duplicate event once', function (): void {
    $event = ($this->checkoutCompleted)();

    stripeWebhook($event)->assertJson(['status' => 'processed']);
    stripeWebhook($event)->assertOk()->assertJson(['status' => 'duplicate']);

    expect(($this->payments)())->toHaveCount(1)
        ->and(StripeWebhookEvent::query()->where('stripe_event_id', $event['id'])->count())->toBe(1);
});

it('records a payment intent once even when Stripe sends it in two events', function (): void {
    $first = ($this->checkoutCompleted)();
    $second = [...$first, 'id' => 'evt_other'];

    stripeWebhook($first);
    stripeWebhook($second)->assertJson(['status' => 'processed']);

    expect(($this->payments)())->toHaveCount(1);
});

it('adds an online payment to an earlier partial cash payment', function (): void {
    actingInSchool($this->admin, $this->school)->post(route('invoices.payments.store', $this->invoice), ['amount' => '400', 'method' => 'cash', 'paid_on' => '2026-09-10']);
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    stripeWebhook(($this->checkoutCompleted)(30_000));
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    stripeWebhook(($this->checkoutCompleted)(50_000));
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(($this->payments)()->pluck('amount')->all())->toBe([40_000, 30_000, 50_000]);
});

it('records a payment Stripe took even if the invoice was paid in the meantime', function (): void {
    actingInSchool($this->admin, $this->school)->post(route('invoices.payments.store', $this->invoice), ['amount' => '1200', 'method' => 'cash', 'paid_on' => '2026-09-10']);

    stripeWebhook(($this->checkoutCompleted)());

    expect(($this->payments)())->toHaveCount(2)
        ->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(inSchool($this->school, fn () => $this->invoice->balanceMoney()->minor))->toBe(-120_000);
});

it('ignores sessions that are unpaid, for unknown invoices or in another currency', function (array $overrides): void {
    stripeWebhook(($this->checkoutCompleted)(120_000, $overrides))->assertOk();

    expect(($this->payments)())->toBeEmpty()
        ->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::Issued);
})->with([
    'unpaid' => [['payment_status' => 'unpaid']],
    'unknown school' => [['metadata' => ['school_id' => '999999']]],
    'unknown invoice' => [['metadata' => ['invoice_id' => '999999']]],
    'other currency' => [['currency' => 'usd']],
]);

it('never records a payment on another school\'s invoice', function (): void {
    $other = setUpFees();
    generateInvoices($other['school'], $other['term']);

    // Metadata naming school A with school B's invoice id: B's invoice is not found in A.
    $foreignInvoiceId = inSchool($other['school'], fn () => Invoice::query()->value('id'));
    stripeWebhook(($this->checkoutCompleted)(120_000, ['metadata' => ['invoice_id' => (string) $foreignInvoiceId]]))->assertOk();

    expect(inSchool($other['school'], fn () => Payment::query()->count()))->toBe(0)
        ->and(($this->payments)())->toBeEmpty();
});

it('applies refunds from the running refunded total', function (): void {
    $event = ($this->checkoutCompleted)();
    stripeWebhook($event);
    $paymentIntent = $event['data']['object']['payment_intent'];

    $refund = fn (int $refunded): array => [
        'id' => 'evt_'.Str::random(12),
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $paymentIntent, 'amount' => 120_000, 'amount_refunded' => $refunded]],
    ];

    stripeWebhook($refund(20_000))->assertOk();
    expect(($this->payments)()->sole()->refunded_amount)->toBe(20_000)
        ->and($this->invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    stripeWebhook($refund(120_000));
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Issued);

    // An older event arriving late does not undo the larger refund.
    stripeWebhook($refund(20_000));
    expect(($this->payments)()->sole()->refunded_amount)->toBe(120_000);
});

it('acknowledges failed payments and other events without changes', function (string $type): void {
    stripeWebhook([
        'id' => 'evt_'.Str::random(12),
        'type' => $type,
        'data' => ['object' => ['id' => 'pi_1', 'metadata' => ['school_id' => (string) $this->school->id, 'invoice_id' => (string) $this->invoice->id]]],
    ])->assertOk()->assertJson(['status' => 'processed']);

    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Issued)
        ->and(($this->payments)())->toBeEmpty();
})->with(['payment_intent.payment_failed', 'customer.created']);
