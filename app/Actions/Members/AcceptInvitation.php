<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AcceptInvitation
{
    /**
     * @param  array{name?: string, password?: string}  $account  Required when the invited user has no password yet.
     */
    public function handle(Membership $membership, array $account = []): User
    {
        return DB::transaction(function () use ($membership, $account): User {
            $user = $membership->user;

            if (! $user->hasPassword()) {
                $user->fill([
                    'name' => $account['name'] ?? $user->name,
                    'password' => $account['password'] ?? throw new InvalidArgumentException('A password is required.'),
                ]);
            }

            // Following the emailed link proves the address belongs to them.
            $user->email_verified_at ??= now();
            $user->save();

            $membership->update(['status' => MembershipStatus::Active]);

            return $user;
        });
    }
}
