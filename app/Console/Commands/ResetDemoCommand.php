<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Demo\ResetDemo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:reset')]
#[Description('Rebuild the public demo from the demo seed (only with DEMO_MODE=true)')]
class ResetDemoCommand extends Command
{
    public function handle(ResetDemo $reset): int
    {
        // It empties every table: never on a server that holds real schools.
        if (! config('schooldesk.demo.enabled')) {
            $this->error('DEMO_MODE is off: refusing to reset this database.');

            return self::FAILURE;
        }

        $reset->handle();
        $this->info('The demo has been reset.');

        return self::SUCCESS;
    }
}
