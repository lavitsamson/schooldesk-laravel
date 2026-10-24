<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Models\Membership;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Removes a member (or revokes a pending invitation) and their roles in that school only.
 * The user account itself stays, because it may belong to other schools.
 */
final class RemoveMember
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Membership $membership): void
    {
        DB::transaction(function () use ($membership): void {
            $user = $membership->user;

            $this->currentSchool->run($membership->school, function () use ($user): void {
                $user->unsetRelation('roles');
                $user->syncRoles([]);
            });

            $membership->delete();
        });
    }
}
