<?php

declare(strict_types=1);

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

// Horizon's metrics (throughput, runtime) need regular snapshots.
Schedule::command(SnapshotCommand::class)->everyFiveMinutes();
