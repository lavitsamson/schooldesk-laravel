<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Membership;
use App\Models\School;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\CrossSchoolRoutes;

/*
 * A school admin of School A asks for School B's records: always 404, never 403, so that a
 * guessed id does not even reveal that the record exists.
 */

dataset('cross-school routes', fn (): array => array_keys(CrossSchoolRoutes::cases()));

it('returns 404 for another school\'s record', function (string $routeName): void {
    $schoolA = School::factory()->completedSetup()->create();
    $schoolB = School::factory()->completedSetup()->create();
    $admin = memberOf($schoolA, Role::SchoolAdmin);

    [$method, $uri] = CrossSchoolRoutes::cases()[$routeName]($schoolB);
    $membershipsInB = Membership::query()->where('school_id', $schoolB->id)->count();

    actingInSchool($admin, $schoolA)->{$method}($uri)->assertNotFound();

    expect(Membership::query()->where('school_id', $schoolB->id)->count())->toBe($membershipsInB);
})->with('cross-school routes');

it('has an isolation case or an exemption for every route with parameters', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => $route->parameterNames() !== [])
        ->map(fn (RoutingRoute $route): string => $route->getName() ?? $route->uri());

    $uncovered = $routes
        ->reject(fn (string $name): bool => array_key_exists($name, CrossSchoolRoutes::cases())
            || array_key_exists($name, CrossSchoolRoutes::apiCases())
            || CrossSchoolRoutes::isExempt($name))
        ->values()
        ->all();

    expect($uncovered)->toBe([], 'Add these routes to tests/Support/CrossSchoolRoutes.php');
});

it('does not keep stale exemptions or cases', function (): void {
    $names = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route): ?string => $route->getName());
    $listed = [...array_keys(CrossSchoolRoutes::cases()), ...array_keys(CrossSchoolRoutes::apiCases()), ...array_keys(CrossSchoolRoutes::EXEMPT)];

    $stale = array_values(array_filter(
        $listed,
        fn (string $name): bool => ! $names->contains($name) && ! str_starts_with($name, 'storage.'),
    ));

    expect($stale)->toBe([]);
});
