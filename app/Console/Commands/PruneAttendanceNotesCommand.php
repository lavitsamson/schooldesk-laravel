<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Gdpr\PruneAttendanceNotes;
use App\Models\School;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gdpr:prune-attendance-notes')]
#[Description('Delete attendance notes older than each school\'s retention setting')]
class PruneAttendanceNotesCommand extends Command
{
    public function handle(PruneAttendanceNotes $prune): int
    {
        School::query()->orderBy('id')->each(function (School $school) use ($prune): void {
            $deleted = $prune->handle($school);

            if ($deleted > 0) {
                $this->line("{$school->name}: {$deleted} notes deleted.");
            }
        });

        return self::SUCCESS;
    }
}
