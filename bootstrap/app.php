<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSchoolMembership;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentSchool;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // Before Inertia, so the shared props can include the current school.
            SetCurrentSchool::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route model binding must already see the school, or school-owned records cannot be
        // resolved (and another school's id must resolve to a 404, not an error). Rate limits
        // are per school, so throttling must see it too; ThrottleRequests runs before
        // SubstituteBindings, so this covers both.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: SetCurrentSchool::class,
        );

        $middleware->alias([
            'school' => EnsureSchoolMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
