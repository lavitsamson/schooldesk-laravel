<?php

declare(strict_types=1);

namespace App\Actions\Api;

use App\Enums\Role;
use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Signs a parent in to the API: a Sanctum token for one device and one school (ADR 0009).
 *
 * The school is the parent's only school, or the one they choose (school_id) when they belong
 * to several; either way it must be an active membership in which they are a parent. Every
 * later request acts for that school only (SetCurrentSchool reads it from the token).
 */
final class IssueApiToken
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @throws ValidationException when the credentials or the school do not fit
     * @throws AuthorizationException when the user is not a parent in that school
     */
    public function handle(string $email, string $password, string $deviceName, ?int $schoolId): NewAccessToken
    {
        $user = User::query()->where('email', trim($email))->first();

        // The same answer whether the address is unknown, has no password yet or is wrong.
        if ($user === null || ! $user->hasPassword() || ! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => __('Please verify your email address first.')]);
        }

        $school = $this->school($user, $schoolId);

        $isParent = $this->currentSchool->run($school, fn (): bool => $user->unsetRelation('roles')->hasRole(Role::Parent));
        if (! $isParent) {
            throw new AuthorizationException(__('The API is for parents. Please use the web app.'));
        }

        $plainText = $user->generateTokenString();

        /** @var PersonalAccessToken $token */
        $token = $user->tokens()->create([
            'school_id' => $school->id,
            'name' => $deviceName,
            'token' => hash('sha256', $plainText),
            'abilities' => ['parent'],
            'expires_at' => now()->addDays((int) config('schooldesk.api.token_days')),
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainText);
    }

    private function school(User $user, ?int $schoolId): School
    {
        if ($schoolId !== null) {
            return $user->activeSchools()->whereKey($schoolId)->first()
                ?? throw ValidationException::withMessages(['school_id' => __('You are not a member of this school.')]);
        }

        $schools = $user->activeSchools()->orderBy('name')->get();

        return match ($schools->count()) {
            0 => throw ValidationException::withMessages(['email' => __('Your account has no school yet.')]),
            1 => $schools->firstOrFail(),
            default => throw ValidationException::withMessages([
                'school_id' => __('You belong to several schools. Choose one: :schools.', [
                    'schools' => $schools->map(fn (School $s): string => "{$s->id} ({$s->name})")->implode(', '),
                ]),
            ]),
        };
    }
}
