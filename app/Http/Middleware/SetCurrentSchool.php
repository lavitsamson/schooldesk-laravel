<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the school a request acts for. Never from request input:
 * - web: the membership the user selected (kept in the session). A user with exactly one
 *   school gets it selected automatically;
 * - API: the school the Sanctum token was issued for (ADR 0009).
 *
 * Either way the membership must still be active, so removing a member takes effect at once.
 */
class SetCurrentSchool
{
    public const SESSION_KEY = 'current_school_id';

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentSchool->forget();

        $user = $request->user();

        if ($user instanceof User) {
            // Null on web requests: Sanctum's docblock says otherwise.
            /** @var PersonalAccessToken|null $token */
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $this->select($user, $user->activeSchools()->whereKey($token->school_id)->first());
            } elseif ($request->hasSession()) {
                $school = $this->select($user, $this->resolve($user, $request->session()->get(self::SESSION_KEY)));

                if ($school !== null) {
                    $request->session()->put(self::SESSION_KEY, $school->id);
                } else {
                    $request->session()->forget(self::SESSION_KEY);
                }
            }
        }

        return $next($request);
    }

    private function select(User $user, ?School $school): ?School
    {
        if ($school !== null) {
            $this->currentSchool->set($school);

            // Roles loaded earlier on this user instance may belong to another school.
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $school;
    }

    private function resolve(User $user, mixed $selectedId): ?School
    {
        if (is_numeric($selectedId)) {
            $selected = $user->activeSchools()->whereKey((int) $selectedId)->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        $schools = $user->activeSchools()->limit(2)->get();

        return $schools->count() === 1 ? $schools->first() : null;
    }
}
