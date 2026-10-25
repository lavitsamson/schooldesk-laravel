<?php

declare(strict_types=1);

use App\Actions\Guardians\NotifyPrimaryGuardian;
use App\Jobs\SendOverdueReminder;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use App\Support\CurrentSchool;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
 * The autumn invoices are due on 31 August 2026: the first reminder goes out on
 * 3 September, the second on 10 September.
 */
beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-08-05 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    generateInvoices($this->school, $this->term);
    $this->ada = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[0]->id)->sole());
});

it('sends the first reminder 3 days after the due date and the second after 10, once each', function (): void {
    $this->travelTo('2026-09-02 07:00:00');
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo('2026-09-03 07:00:00');
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->parent, InvoiceOverdue::class, 1);
    Notification::assertSentTo($this->parent, InvoiceOverdue::class, fn (InvoiceOverdue $n): bool => $n->reminder === 1 && $n->balance->minor === 120_000);
    expect($this->ada->refresh()->reminders_sent)->toBe(1);

    $this->travelTo('2026-09-10 07:00:00');
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();
    $this->travelTo('2026-09-20 07:00:00');
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->parent, InvoiceOverdue::class, 2);
    expect($this->ada->refresh()->reminders_sent)->toBe(2);
});

it('sends only the second reminder to an invoice found 10 days late', function (): void {
    Queue::fake();
    $this->travelTo('2026-09-15 07:00:00');

    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();

    Queue::assertPushed(SendOverdueReminder::class, fn (SendOverdueReminder $job): bool => $job->invoiceId === $this->ada->id && $job->reminder === 2);
    Queue::assertPushed(SendOverdueReminder::class, 3);
});

it('mails guardians without a portal account', function (): void {
    $guardian = Guardian::factory()->for($this->school)->create(['email' => 'ben.parent@example.com']);
    $this->students[1]->guardians()->attach($guardian, ['is_primary' => true]);
    $this->travelTo('2026-09-03 07:00:00');

    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();

    Notification::assertSentOnDemand(InvoiceOverdue::class, fn (InvoiceOverdue $n, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['ben.parent@example.com' => $guardian->name]);
});

it('skips paid and void invoices, and those paid before the job runs', function (): void {
    Queue::fake();
    actingInSchool($this->admin, $this->school)->post(route('invoices.payments.store', $this->ada), ['amount' => '1200', 'method' => 'cash', 'paid_on' => '2026-08-05']);
    $bens = inSchool($this->school, fn () => Invoice::query()->where('student_id', $this->students[1]->id)->sole());
    actingInSchool($this->admin, $this->school)->post(route('invoices.void', $bens));

    $this->travelTo('2026-09-03 07:00:00');
    $this->artisan('fees:send-overdue-reminders')->assertSuccessful();

    // Only Cleo's invoice is still open.
    Queue::assertPushed(SendOverdueReminder::class, 1);

    // A queued reminder for an invoice paid since then sends nothing.
    (new SendOverdueReminder($this->school->id, $this->ada->id, 1))->handle(app(CurrentSchool::class), app(NotifyPrimaryGuardian::class));
    Notification::assertNothingSent();
});
