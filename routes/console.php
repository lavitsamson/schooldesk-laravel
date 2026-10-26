<?php

declare(strict_types=1);

use App\Console\Commands\GenerateTermInvoicesCommand;
use App\Console\Commands\PruneAttendanceNotesCommand;
use App\Console\Commands\SendOverdueRemindersCommand;
use App\Models\StudentExport;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Console\SnapshotCommand;
use Laravel\Sanctum\Console\Commands\PruneExpired;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes old import batches and expired data exports, with their files (see the models' prunable()).
Schedule::command(PruneCommand::class)->daily()->onOneServer();
Schedule::command(PruneCommand::class, ['--model' => [StudentExport::class]])->hourly()->onOneServer();

// GDPR: attendance notes older than each school's retention setting are deleted.
Schedule::command(PruneAttendanceNotesCommand::class)->dailyAt('03:00')->onOneServer()->withoutOverlapping();

// Fees: invoices for terms starting within 14 days at night, payment reminders in the morning.
Schedule::command(GenerateTermInvoicesCommand::class)->dailyAt('02:00')->onOneServer()->withoutOverlapping();
Schedule::command(SendOverdueRemindersCommand::class)->dailyAt('07:00')->onOneServer()->withoutOverlapping();

// Expired parent API tokens are deleted a day after they expire.
Schedule::command(PruneExpired::class, ['--hours' => 24])->daily()->onOneServer();

// Horizon's metrics (throughput, runtime) need regular snapshots.
Schedule::command(SnapshotCommand::class)->everyFiveMinutes();
