<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Services\StripeService;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->travelTo('2026-09-10 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    generateInvoices($this->school, $this->term);
    $this->invoice = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[0]->id)->sole());
    $this->asParent = fn () => actingInSchool($this->parent, $this->school);
});

it('sends the parent to Stripe Checkout for the open balance', function (): void {
    actingInSchool($this->admin, $this->school)->post(route('invoices.payments.store', $this->invoice), ['amount' => '200', 'method' => 'cash', 'paid_on' => '2026-09-10']);

    $this->mock(StripeService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('createCheckoutSession')->once()->withArgs(function (array $params): bool {
            expect($params['mode'])->toBe('payment')
                ->and($params['customer_email'])->toBe($this->parent->email)
                ->and($params['line_items'][0]['price_data'])->toMatchArray(['currency' => 'eur', 'unit_amount' => 100_000])
                // The invoice number and term, but not the student's name.
                ->and($params['line_items'][0]['price_data']['product_data']['name'])->toContain('SD-2026-000001')
                ->and(json_encode($params))->not->toContain('Ada')
                ->and($params['metadata'])->toBe([
                    'school_id' => (string) $this->school->id,
                    'invoice_id' => (string) $this->invoice->id,
                    'user_id' => (string) $this->parent->id,
                ])
                ->and($params['payment_intent_data']['metadata'])->toBe($params['metadata'])
                ->and($params['success_url'])->toContain('checkout=success');

            return true;
        })->andReturn(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
    });

    ($this->asParent)()->get(route('invoices.show', $this->invoice))
        ->assertInertia(fn (Assert $page) => $page->where('can.pay', true));

    ($this->asParent)()->post(route('invoices.checkout', $this->invoice), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/cs_test_1');
});

it('shows the outcome when the parent comes back from Checkout', function (): void {
    ($this->asParent)()->get(route('invoices.show', ['invoice' => $this->invoice, 'checkout' => 'success']))
        ->assertInertia(fn (Assert $page) => $page->where('checkout', 'success'));
    ($this->asParent)()->get(route('invoices.show', ['invoice' => $this->invoice, 'checkout' => '<script>']))
        ->assertInertia(fn (Assert $page) => $page->where('checkout', null));
});

it('refuses online payment without Stripe keys', function (): void {
    ($this->asParent)()->post(route('invoices.checkout', $this->invoice))->assertSessionHasErrors('payment');
});

it('lets only the child\'s guardians pay, and only open invoices', function (): void {
    $this->mock(StripeService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldNotReceive('createCheckoutSession');
    });

    $bens = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[1]->id)->sole());
    ($this->asParent)()->post(route('invoices.checkout', $bens))->assertForbidden();
    actingInSchool($this->admin, $this->school)->post(route('invoices.checkout', $this->invoice))->assertForbidden();

    actingInSchool($this->admin, $this->school)->post(route('invoices.payments.store', $this->invoice), ['amount' => '1200', 'method' => 'cash', 'paid_on' => '2026-09-10']);
    ($this->asParent)()->post(route('invoices.checkout', $this->invoice))->assertForbidden();
});
