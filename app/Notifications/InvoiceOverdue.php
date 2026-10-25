<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The first (3 days after the due date) or second (10 days) overdue reminder. Sent by the
 * queued SendOverdueReminder job, so not queued itself.
 */
class InvoiceOverdue extends Notification
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $reminder,
        public readonly Money $balance,
    ) {}

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
        $balance = $this->balance->format($school->locale->value);
        $subject = $this->reminder === 1 ? 'Payment reminder' : 'Second payment reminder';

        $mail = (new MailMessage)
            ->subject("{$subject}: invoice {$this->invoice->number}")
            ->line("Invoice {$this->invoice->number} ({$this->invoice->student->first_name}, {$this->invoice->term->name}) was due on {$this->invoice->due_on->format('j F Y')}, and {$balance} is still open.")
            ->line('If you have paid in the last few days, thank you, and please ignore this reminder.');

        return $notifiable instanceof User
            ? $mail->action('Pay the invoice', route('invoices.show', $this->invoice))
            : $mail->line("Please pay by bank transfer quoting {$this->invoice->number}, or at the school office.");
    }

    /**
     * @return array{invoice_id: int, number: string, reminder: int, balance: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            'reminder' => $this->reminder,
            'balance' => $this->balance->minor,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'invoice-overdue';
    }
}
