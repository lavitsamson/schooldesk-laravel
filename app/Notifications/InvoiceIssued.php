<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by the queued SendInvoiceIssuedNotification job, so not queued itself. Guardians with a
 * portal account get a link to pay online; the others pay by bank transfer or at the office.
 */
class InvoiceIssued extends Notification
{
    public function __construct(public readonly Invoice $invoice) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $school = $this->invoice->school;
        $name = $this->invoice->student->first_name;
        $total = $this->invoice->totalMoney()->format($school->locale->value);

        $mail = (new MailMessage)
            ->subject("Invoice {$this->invoice->number} for {$name}: {$this->invoice->term->name}")
            ->line("{$school->name} has issued invoice {$this->invoice->number} for {$name}'s fees for the {$this->invoice->term->name}.")
            ->line("Total: {$total}, due on {$this->invoice->due_on->format('j F Y')}.");

        return $notifiable instanceof User
            ? $mail->action('View and pay the invoice', route('invoices.show', $this->invoice))
            : $mail->line("Please pay by bank transfer quoting {$this->invoice->number}, or at the school office.");
    }

    /**
     * @return array{invoice_id: int, number: string, student_id: int, first_name: string, term: string, total: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'student_id' => $this->invoice->student_id,
            'first_name' => $this->invoice->student->first_name,
            'term' => $this->invoice->term->name,
            'total' => $this->invoice->total,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'invoice-issued';
    }
}
