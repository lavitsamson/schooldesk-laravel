<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by the queued GeneratePaymentReceipt job once the receipt is stored. The receipt itself
 * stays behind the portal login.
 */
class PaymentReceived extends Notification
{
    public function __construct(public readonly Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;
        $school = $invoice->school;
        $amount = (new Money($this->payment->amount, $school->currency))->format($school->locale->value);

        $mail = (new MailMessage)
            ->subject("Payment received: invoice {$invoice->number}")
            ->line("{$school->name} has received your payment of {$amount} for invoice {$invoice->number} ({$invoice->student->first_name}, {$invoice->term->name}).")
            ->line('Thank you.');

        return $notifiable instanceof User
            ? $mail->action('View the invoice and receipt', route('invoices.show', $invoice))
            : $mail;
    }

    /**
     * @return array{payment_id: int, invoice_id: int, number: string, amount: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'number' => $this->payment->invoice->number,
            'amount' => $this->payment->amount,
        ];
    }

    public function databaseType(object $notifiable): string
    {
        return 'payment-received';
    }
}
