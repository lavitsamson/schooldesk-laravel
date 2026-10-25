<?php

declare(strict_types=1);

use App\Models\FeeStructure;
use App\Models\Invoice;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    $this->travelTo('2026-08-05 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    FeeStructure::factory()->forClass($this->otherClass)->create(['name' => 'Lab fee', 'amount' => 5_000]);
    // Due on 31 August; "today" is 10 September.
    generateInvoices($this->school, $this->term);
    $this->travelTo('2026-09-10 10:00:00');
    $this->invoiceOf = fn (int $index) => inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[$index]->id)->sole());
    $this->pay = fn (int $index, string $amount) => actingInSchool($this->admin, $this->school)
        ->post(route('invoices.payments.store', ($this->invoiceOf)($index)), ['amount' => $amount, 'method' => 'cash', 'paid_on' => '2026-09-01']);
});

it('sums collected and outstanding per class and in total', function (): void {
    ($this->pay)(0, '1200');   // Ada: paid
    ($this->pay)(1, '200');    // Ben: 1,000.00 open, overdue
    // Cleo (Grade 8 A): 1,250.00 open, overdue

    actingInSchool($this->admin, $this->school)->get(route('fees.index', ['term' => $this->term->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fees/Index')
            ->where('term.name', 'Autumn term')
            ->where('feeStructures', 2)
            ->where('onlinePayments', false)
            ->where('dashboard.classes', [
                ['id' => $this->class->id, 'name' => 'Grade 7 A', 'invoices' => 2, 'invoiced' => 240_000, 'collected' => 140_000, 'outstanding' => 100_000, 'overdue' => 100_000, 'paid' => 1],
                ['id' => $this->otherClass->id, 'name' => 'Grade 8 A', 'invoices' => 1, 'invoiced' => 125_000, 'collected' => 0, 'outstanding' => 125_000, 'overdue' => 125_000, 'paid' => 0],
            ])
            ->where('dashboard.totals', ['invoices' => 3, 'invoiced' => 365_000, 'collected' => 140_000, 'outstanding' => 225_000, 'overdue' => 225_000, 'paid' => 1]));
});

it('leaves out void invoices and never counts a credit as negative outstanding', function (): void {
    actingInSchool($this->admin, $this->school)->post(route('invoices.void', ($this->invoiceOf)(2)));
    ($this->pay)(0, '1200');
    // A second payment (e.g. a card payment arriving after cash) overpays Ada's invoice.
    inSchool($this->school, fn () => ($this->invoiceOf)(0)->payments()->create(['amount' => 10_000, 'method' => 'stripe', 'paid_at' => now()]));

    actingInSchool($this->admin, $this->school)->get(route('fees.index', ['term' => $this->term->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboard.classes', 1)
            ->where('dashboard.totals.invoiced', 240_000)
            ->where('dashboard.totals.collected', 130_000)
            ->where('dashboard.totals.outstanding', 120_000));
});

it('shows an empty term', function (): void {
    actingInSchool($this->admin, $this->school)->get(route('fees.index', ['term' => $this->nextTerm->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.classes', [])
            ->where('dashboard.totals.invoiced', 0));
});
