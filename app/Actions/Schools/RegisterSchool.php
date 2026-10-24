<?php

declare(strict_types=1);

namespace App\Actions\Schools;

use App\Actions\Members\GrantRole;
use App\Enums\Locale;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-service sign-up: creates the user, their school and makes them its school admin.
 * School details are completed afterwards in the setup wizard.
 */
final class RegisterSchool
{
    public function __construct(private readonly GrantRole $grantRole) {}

    /**
     * @param  array{name: string, email: string, password: string, school_name: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $school = School::create([
                'name' => $data['school_name'],
                'slug' => $this->uniqueSlug($data['school_name']),
                'country' => 'DE',
                'timezone' => 'Europe/Berlin',
                'locale' => Locale::English,
                'currency' => 'EUR',
            ]);

            $school->memberships()->create([
                'user_id' => $user->id,
                'status' => MembershipStatus::Active,
            ]);

            $this->grantRole->handle($user, $school, Role::SchoolAdmin);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;

        while (School::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
