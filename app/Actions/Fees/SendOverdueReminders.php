<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Jobs\SendOverdueReminder;
use App\Models\Invoice;
use App\Support\CurrentSchool;

/**
 * Queues the overdue reminders due today in the current school: the first 3 days after the due
 * date, the second after 10 (config schooldesk.fees.reminder_days). A day the scheduler missed
 * is caught up the next day; an invoice found 10 days late gets only the second reminder, not
 * both at once. Each job claims its reminder, so running this twice sends nothing twice.
 */
final class SendOverdueReminders
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @return int the number of reminders queued
     */
    public function handle(): int
    {
        $school = $this->currentSchool->get();
        $today = $school->today();
        /** @var array{0: int, 1: int} $days */
        $days = config('schooldesk.fees.reminder_days');
        $secondFrom = $today->subDays($days[1]);
        $queued = 0;

        Invoice::query()
            ->whereIn('status', InvoiceStatus::open())
            ->where('due_on', '<=', $today->subDays($days[0])->toDateString())
            ->where('reminders_sent', '<', 2)
            ->chunkById(500, function ($invoices) use ($school, $secondFrom, &$queued): void {
                foreach ($invoices as $invoice) {
                    /** @var Invoice $invoice */
                    $reminder = $invoice->due_on->lte($secondFrom) ? 2 : 1;

                    if ($invoice->reminders_sent < $reminder) {
                        SendOverdueReminder::dispatch($school->id, $invoice->id, $reminder);
                        $queued++;
                    }
                }
            });

        return $queued;
    }
}
