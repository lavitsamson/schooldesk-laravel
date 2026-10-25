<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

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
        $total = $this->invoice->totalMoney()->format(App::getLocale());

        $mail = (new MailMessage)
            ->subject(__('Invoice :number for :name: :term', $replace = [
                'number' => $this->invoice->number,
                'name' => $name,
                'term' => $this->invoice->term->name,
                'school' => $school->name,
            ]))
            ->line(__(":school has issued invoice :number for :name's fees for the :term.", $replace))
            ->line(__('Total: :total, due on :date.', ['total' => $total, 'date' => LocalDate::long($this->invoice->due_on)]));

        return $notifiable instanceof User
            ? $mail->action(__('View and pay the invoice'), route('invoices.show', $this->invoice))
            : $mail->line(__('Please pay by bank transfer quoting :number, or at the school office.', $replace));
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
