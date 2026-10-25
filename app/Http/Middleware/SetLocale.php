<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Models\User;
use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a request: the user's own choice, else the current school's language,
 * else (guests, users without a school) the browser's preferred supported language.
 * Runs after SetCurrentSchool.
 */
class SetLocale
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request)->value);

        return $next($request);
    }

    private function resolve(Request $request): Locale
    {
        $user = $request->user();

        if ($user instanceof User && ($own = Locale::tryFrom((string) $user->locale)) !== null) {
            return $own;
        }

        if ($this->currentSchool->check()) {
            return $this->currentSchool->get()->locale;
        }

        return Locale::tryFrom((string) $request->getPreferredLanguage(Locale::values()))
            ?? Locale::English;
    }
}
