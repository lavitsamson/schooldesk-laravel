<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes that need a school. SetCurrentSchool has already checked the membership;
 * without a school, the user picks one (or learns they have none).
 */
class EnsureSchoolMembership
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->currentSchool->check()) {
            // API tokens name their school; without an active membership there, the token is no use.
            abort_if($request->bearerToken() !== null, Response::HTTP_FORBIDDEN, 'Your membership of this token\'s school has ended.');
            abort_if($request->expectsJson(), Response::HTTP_FORBIDDEN, 'No school selected.');

            return redirect()->route('schools.select');
        }

        return $next($request);
    }
}
