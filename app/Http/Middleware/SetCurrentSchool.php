<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the school a web request acts for from the membership the user selected (kept in
 * the session). Never from request input. A user with exactly one school gets it selected
 * automatically. The Sanctum (token) variant arrives with the parent API.
 */
class SetCurrentSchool
{
    public const SESSION_KEY = 'current_school_id';

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentSchool->forget();

        $user = $request->user();

        if ($user instanceof User && $request->hasSession()) {
            $school = $this->resolve($user, $request->session()->get(self::SESSION_KEY));

            if ($school !== null) {
                $this->currentSchool->set($school);
                $request->session()->put(self::SESSION_KEY, $school->id);

                // Roles loaded earlier on this user instance may belong to another school.
                $user->unsetRelation('roles')->unsetRelation('permissions');
            } else {
                $request->session()->forget(self::SESSION_KEY);
            }
        }

        return $next($request);
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
