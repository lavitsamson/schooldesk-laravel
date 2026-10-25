<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Jobs\GeneratePaymentReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2026-08-05 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    // Generated in August, due on 31 August; "today" is 10 September.
    generateInvoices($this->school, $this->term);
    $this->travelTo('2026-09-10 10:00:00');
    $this->invoice = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[0]->id)->sole());
    $this->as = fn (?User $user = null) => actingInSchool($user ?? $this->admin, $this->school);
    $this->pay = fn (array $input = []) => ($this->as)()->post(route('invoices.payments.store', $this->invoice), [
        'amount' => '500',
        'method' => 'cash',
        'paid_on' => '2026-09-10',
        ...$input,
    ]);
});

it('lists a term\'s invoices with filters', function (): void {
    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->has('invoices.data', 3)
            ->where('invoices.data.0.number', 'SD-2026-000001')
            ->where('invoices.data.0.student.name', 'Ada Test')
            ->where('invoices.data.0.class', 'Grade 7 A')
            ->where('invoices.data.0.balance', 120_000)
            ->where('invoices.data.0.overdue', true)
            ->where('filters.status', 'all'));

    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id, 'class_id' => $this->otherClass->id]))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.student.name', 'Cleo Test'));

    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id, 'search' => 'ben']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.student.name', 'Ben Test'));

    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id, 'search' => '000003']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1)->where('invoices.data.0.number', 'SD-2026-000003'));

    // Due on 31 August: overdue on 10 September.
    ($this->pay)(['amount' => '1200']);
    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id, 'status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 2)->where('invoices.data.0.overdue', true));
    ($this->as)()->get(route('invoices.index', ['term' => $this->term->id, 'status' => 'paid']))
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));

    ($this->as)()->get(route('invoices.index', ['status' => 'unknown']))->assertSessionHasErrors('status');
});

it('shows an invoice with signed PDF and receipt links', function (): void {
    ($this->pay)(['amount' => '200', 'reference' => 'Office till']);

    ($this->as)()->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('invoice.number', 'SD-2026-000001')
            ->where('invoice.status', 'partially_paid')
            ->where('invoice.total', 120_000)
            ->where('invoice.paid', 20_000)
            ->where('invoice.balance', 100_000)
            ->where('invoice.balance_input', '1000.00')
            ->where('invoice.overdue', true)
            ->where('invoice.items.0.description', 'Tuition')
            ->where('invoice.payments.0.method', 'cash')
            ->where('invoice.payments.0.reference', 'Office till')
            ->where('invoice.payments.0.receipt_url', fn (string $url): bool => str_contains($url, 'signature='))
            ->where('invoice.pdf_url', fn (string $url): bool => str_contains($url, 'signature='))
            ->where('can', ['manage' => true, 'recordPayment' => true, 'void' => false, 'pay' => false]));
});

it('renders the invoice PDF for signed links only', function (): void {
    $url = URL::temporarySignedRoute('invoices.pdf', now()->addMinutes(5), ['invoice' => $this->invoice]);

    $response = ($this->as)()->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');

    ($this->as)()->get(route('invoices.pdf', $this->invoice))->assertForbidden();
});

it('records partial and full cash payments', function (): void {
    Queue::fake();

    ($this->pay)(['amount' => '500,50'])->assertSessionHasNoErrors();
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::PartiallyPaid);

    ($this->pay)(['amount' => '699.50', 'method' => 'bank_transfer', 'paid_on' => '2026-09-01', 'reference' => 'SEPA 123'])->assertSessionHasNoErrors();
    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Paid);

    $payments = inSchool($this->school, fn () => Payment::query()->orderBy('id')->get());
    expect($payments->pluck('amount')->all())->toBe([50_050, 69_950])
        ->and($payments[0]->recorded_by)->toBe($this->admin->id)
        ->and($payments[1]->method)->toBe(PaymentMethod::BankTransfer)
        ->and($payments[1]->reference)->toBe('SEPA 123')
        // An earlier day is recorded at noon in the school's timezone (Berlin, UTC+2 in September).
        ->and($payments[1]->paid_at->toIso8601String())->toBe('2026-09-01T10:00:00+00:00');
    Queue::assertPushed(GeneratePaymentReceipt::class, 2);

    // Paid in full: nothing more can be recorded.
    ($this->pay)(['amount' => '1'])->assertForbidden();
});

it('validates manual payments', function (array $input, string $field): void {
    ($this->pay)($input)->assertSessionHasErrors($field);

    expect(inSchool($this->school, fn () => Payment::query()->count()))->toBe(0);
})->with([
    'more than the balance' => [['amount' => '1200.01'], 'amount'],
    'zero' => [['amount' => '0'], 'amount'],
    'not an amount' => [['amount' => 'lots'], 'amount'],
    'card payments come from Stripe' => [['method' => 'stripe'], 'method'],
    'in the future' => [['paid_on' => '2026-09-11'], 'paid_on'],
]);

it('stores the receipt and tells the primary guardian', function (): void {
    Notification::fake();

    ($this->pay)(['amount' => '1200']); // the sync queue runs the receipt job

    $payment = inSchool($this->school, fn () => Payment::query()->sole());
    expect($payment->receipt_path)->toBe("schools/{$this->school->id}/receipts/{$payment->id}.pdf");
    Storage::disk('local')->assertExists($payment->receipt_path);
    Notification::assertSentTo($this->parent, PaymentReceived::class);

    $response = ($this->as)()->get(URL::temporarySignedRoute('payments.receipt', now()->addMinutes(5), ['payment' => $payment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($response->streamedContent())->toStartWith('%PDF');
});

it('renders a missing receipt on download', function (): void {
    $payment = Payment::factory()->for($this->invoice)->create();

    ($this->as)()->get(URL::temporarySignedRoute('payments.receipt', now()->addMinutes(5), ['payment' => $payment]))->assertOk();

    expect($payment->refresh()->receipt_path)->not->toBeNull();
});

it('voids an unpaid invoice only', function (): void {
    ($this->as)()->post(route('invoices.void', $this->invoice))->assertSessionHasNoErrors();

    expect($this->invoice->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and($this->invoice->voided_at)->not->toBeNull();
    ($this->as)()->post(route('invoices.payments.store', $this->invoice), ['amount' => '1', 'method' => 'cash', 'paid_on' => '2026-09-10'])->assertForbidden();

    $paid = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[1]->id)->sole());
    ($this->as)()->post(route('invoices.payments.store', $paid), ['amount' => '10', 'method' => 'cash', 'paid_on' => '2026-09-10']);
    ($this->as)()->post(route('invoices.void', $paid))->assertForbidden();
});

it('shows parents their own children\'s invoices only', function (): void {
    ($this->as)($this->parent)->get(route('family.invoices'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Family')
            ->has('invoices', 1)
            ->where('invoices.0.number', 'SD-2026-000001')
            ->where('invoices.0.term', 'Autumn term'));

    ($this->as)($this->parent)->get(route('invoices.show', $this->invoice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.manage', false)
            ->where('can.recordPayment', false)
            // Stripe is not configured in tests.
            ->where('can.pay', false));

    $bens = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[1]->id)->sole());
    ($this->as)($this->parent)->get(route('invoices.show', $bens))->assertForbidden();
    ($this->as)($this->parent)->get(URL::temporarySignedRoute('invoices.pdf', now()->addMinutes(5), ['invoice' => $bens]))->assertForbidden();
    ($this->as)($this->parent)->get(route('invoices.index'))->assertForbidden();
    ($this->as)($this->parent)->post(route('invoices.payments.store', $this->invoice), ['amount' => '1', 'method' => 'cash', 'paid_on' => '2026-09-10'])->assertForbidden();
});

it('keeps invoices away from teachers', function (): void {
    $teacher = memberOf($this->school, Role::Teacher);

    ($this->as)($teacher)->get(route('invoices.show', $this->invoice))->assertForbidden();
    ($this->as)($teacher)->get(route('family.invoices'))->assertForbidden();
});
