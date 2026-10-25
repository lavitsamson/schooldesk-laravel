<?php

declare(strict_types=1);

use App\Console\Commands\GenerateTermInvoicesCommand;
use App\Console\Commands\SendOverdueRemindersCommand;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Console\SnapshotCommand;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes old import batches and their files (see ImportBatch::prunable()).
Schedule::command(PruneCommand::class)->daily()->onOneServer();

// Fees: invoices for terms starting within 14 days at night, payment reminders in the morning.
Schedule::command(GenerateTermInvoicesCommand::class)->dailyAt('02:00')->onOneServer()->withoutOverlapping();
Schedule::command(SendOverdueRemindersCommand::class)->dailyAt('07:00')->onOneServer()->withoutOverlapping();

// Horizon's metrics (throughput, runtime) need regular snapshots.
Schedule::command(SnapshotCommand::class)->everyFiveMinutes();
