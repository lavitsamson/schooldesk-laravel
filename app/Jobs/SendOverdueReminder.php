<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Guardians\NotifyPrimaryGuardian;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdue;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Sends the n-th overdue reminder for an invoice, at most once: the job claims it
 * (reminders_sent) under a row lock before sending, like absence notifications (ADR 0005).
 * An invoice paid or voided in the meantime gets nothing.
 */
final class SendOverdueReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $invoiceId,
        public readonly int $reminder,
    ) {}

    public function handle(CurrentSchool $currentSchool, NotifyPrimaryGuardian $notify): void
    {
        $currentSchool->runInSchoolLocale($this->schoolId, function () use ($notify): void {
            $invoice = DB::transaction(function (): ?Invoice {
                $invoice = Invoice::query()->lockForUpdate()->find($this->invoiceId);

                if ($invoice === null || ! $invoice->status->isOpen() || $invoice->reminders_sent >= $this->reminder) {
                    return null;
                }

                $invoice->forceFill(['reminders_sent' => $this->reminder])->save();

                return $invoice;
            });

            if ($invoice === null) {
                return;
            }

            $invoice->load(['school', 'term', 'student']);
            $notify->handle($invoice->student, new InvoiceOverdue($invoice, $this->reminder, $invoice->balanceMoney()));
        });
    }
}
