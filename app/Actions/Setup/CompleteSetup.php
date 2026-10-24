<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Models\School;

final class CompleteSetup
{
    public function handle(School $school): void
    {
        $school->forceFill(['setup_completed_at' => now()])->save();
    }
}
