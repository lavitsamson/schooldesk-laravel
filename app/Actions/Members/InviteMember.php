<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invites a person to a school with a role. Creates a password-less user if the email is new.
 * Inviting someone who already has a pending invitation re-sends it (and adds the role).
 */
final class InviteMember
{
    public function __construct(private readonly GrantRole $grantRole) {}

    public function handle(School $school, string $name, string $email, Role $role): Membership
    {
        $membership = DB::transaction(function () use ($school, $name, $email, $role): Membership {
            $user = User::query()->firstOrCreate(['email' => Str::lower($email)], ['name' => $name]);

            $membership = Membership::query()->firstOrNew([
                'school_id' => $school->id,
                'user_id' => $user->id,
            ]);
            $membership->fill(['status' => MembershipStatus::Invited, 'invited_at' => now()])->save();

            $this->grantRole->handle($user, $school, $role);

            return $membership;
        });

        $membership->user->notify(new SchoolInvitation($membership));

        return $membership;
    }
}
