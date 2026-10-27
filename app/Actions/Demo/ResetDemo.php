<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds the public demo from the demo seed (the nightly `demo:reset`). Whatever visitors
 * did is removed: queued jobs, stored files, every table, sessions and the cache. The app is
 * in maintenance mode meanwhile; with the cache maintenance driver every container sees it.
 */
final class ResetDemo
{
    public function handle(): void
    {
        Artisan::call('down', ['--retry' => 60, '--refresh' => 15]);

        try {
            // Queued jobs refer to records that are about to disappear.
            foreach ($this->queues() as $queue) {
                Artisan::call('queue:clear', ['--queue' => $queue, '--force' => true]);
            }

            // Photos, imports, exports and PDFs; the seed renders its report cards anew.
            $disk = Storage::disk('local');
            foreach ($disk->directories() as $directory) {
                $disk->deleteDirectory($directory);
            }

            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);

            // The seed gives users the same ids every night, but new sign-ups reuse the ids of
            // yesterday's: a surviving session could open someone else's school.
            $this->signEveryoneOut();
            Artisan::call('cache:clear');
        } finally {
            Artisan::call('up');
        }
    }

    /** @return list<string> */
    private function queues(): array
    {
        /** @var array<string, array{queue?: list<string>}> $supervisors */
        $supervisors = config('horizon.defaults', []);

        return array_values(array_unique(Arr::flatten(array_map(
            fn (array $supervisor): array => $supervisor['queue'] ?? [],
            $supervisors,
        ))));
    }

    private function signEveryoneOut(): void
    {
        $connection = config('session.connection');

        // Only a Redis database of their own can be emptied without losing queues or cache.
        if (config('session.driver') === 'redis' && $connection === 'sessions') {
            Redis::connection($connection)->command('flushdb');
        }
    }
}
