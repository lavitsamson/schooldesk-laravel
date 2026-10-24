<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Models\Membership;
use App\Notifications\SchoolInvitation;

/**
 * Sends a fresh invitation link. Moving invited_at forward invalidates older links.
 */
final class ResendInvitation
{
    public function handle(Membership $membership): void
    {
        $membership->update(['invited_at' => now()]);

        $membership->user->notify(new SchoolInvitation($membership));
    }
}
