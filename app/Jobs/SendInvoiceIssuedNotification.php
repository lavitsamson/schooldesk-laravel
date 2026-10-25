<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Guardians\NotifyPrimaryGuardian;
use App\Models\Invoice;
use App\Notifications\InvoiceIssued;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells the primary guardian that a new invoice is out. Skipped if the invoice was voided
 * before the job ran.
 */
final class SendInvoiceIssuedNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $invoiceId,
    ) {}

    public function handle(CurrentSchool $currentSchool, NotifyPrimaryGuardian $notify): void
    {
        $currentSchool->runInSchoolLocale($this->schoolId, function () use ($notify): void {
            $invoice = Invoice::query()->with(['school', 'term', 'student'])->find($this->invoiceId);

            if ($invoice === null || ! $invoice->status->isOpen()) {
                return;
            }

            $notify->handle($invoice->student, new InvoiceIssued($invoice));
        });
    }
}
