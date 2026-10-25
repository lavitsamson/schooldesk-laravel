<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fees\SendOverdueReminders;
use App\Models\School;
use App\Support\CurrentSchool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fees:send-overdue-reminders')]
#[Description('Queue the payment reminders for invoices 3 and 10 days past their due date')]
class SendOverdueRemindersCommand extends Command
{
    public function handle(CurrentSchool $currentSchool, SendOverdueReminders $send): int
    {
        School::query()->orderBy('id')->each(function (School $school) use ($currentSchool, $send): void {
            $queued = $currentSchool->runInSchoolLocale($school, fn (): int => $send->handle());

            if ($queued > 0) {
                $this->line("{$school->name}: {$queued} reminders.");
            }
        });

        return self::SUCCESS;
    }
}
