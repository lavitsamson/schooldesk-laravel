<?php

declare(strict_types=1);

namespace App\Actions\Guardians;

use App\Actions\Members\GrantRole;
use App\Actions\Members\InviteMember;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Guardian;
use App\Models\Membership;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gives a guardian access to the parent portal: a school membership with the parent role,
 * linked to the guardian record so the portal knows whose children to show.
 *
 * Someone who is already an active member (a teacher who is also a parent) just gets the
 * parent role; inviting them again would put their membership back to "invited".
 */
final class InviteGuardian
{
    public function __construct(
        private readonly InviteMember $inviteMember,
        private readonly GrantRole $grantRole,
        private readonly CurrentSchool $currentSchool,
    ) {}

    /**
     * @return bool whether an invitation email was sent
     */
    public function handle(Guardian $guardian): bool
    {
        if ($guardian->email === null) {
            throw new InvalidArgumentException('A guardian needs an email address to be invited.');
        }

        $school = $this->currentSchool->get();
        $activeMember = User::query()
            ->where('email', $guardian->email)
            ->whereHas('memberships', fn ($q) => $q->where('school_id', $school->id)->where('status', MembershipStatus::Active))
            ->first();

        if ($activeMember !== null) {
            DB::transaction(function () use ($activeMember, $school, $guardian): void {
                $this->grantRole->handle($activeMember, $school, Role::Parent);
                $guardian->update(['user_id' => $activeMember->id]);
            });

            return false;
        }

        /** @var Membership $membership */
        $membership = $this->inviteMember->handle($school, $guardian->name, $guardian->email, Role::Parent);
        $guardian->update(['user_id' => $membership->user_id]);

        return true;
    }
}
