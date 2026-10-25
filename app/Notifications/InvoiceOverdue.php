<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Support\LocalDate;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

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
        $replace = [
            'number' => $this->invoice->number,
            'name' => $this->invoice->student->first_name,
            'term' => $this->invoice->term->name,
            'date' => LocalDate::long($this->invoice->due_on),
            'balance' => $this->balance->format(App::getLocale()),
        ];

        $mail = (new MailMessage)
            ->subject($this->reminder === 1
                ? __('Payment reminder: invoice :number', $replace)
                : __('Second payment reminder: invoice :number', $replace))
            ->line(__('Invoice :number (:name, :term) was due on :date, and :balance is still open.', $replace))
            ->line(__('If you have paid in the last few days, thank you, and please ignore this reminder.'));

        return $notifiable instanceof User
            ? $mail->action(__('Pay the invoice'), route('invoices.show', $this->invoice))
            : $mail->line(__('Please pay by bank transfer quoting :number, or at the school office.', $replace));
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
