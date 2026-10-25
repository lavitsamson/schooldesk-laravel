<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\School;
use App\Services\StripeService;
use Mockery\MockInterface;
use Tests\Support\CrossSchoolRoutes;

/*
 * The parent API's isolation suite: a parent of School A, with a token for School A, asks for
 * School B's records. Always 404, never 403: a guessed id must not reveal that it exists.
 */

dataset('cross-school api routes', fn (): array => array_keys(CrossSchoolRoutes::apiCases()));

it('returns 404 for another school\'s record through the API', function (string $routeName): void {
    $schoolA = School::factory()->completedSetup()->create();
    $schoolB = School::factory()->completedSetup()->create();
    $parent = memberOf($schoolA, Role::Parent);

    // Stripe must never be asked for another school's invoice.
    $this->mock(StripeService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldNotReceive('createCheckoutSession');
    });

    [$method, $uri] = CrossSchoolRoutes::apiCases()[$routeName]($schoolB);
    $token = apiToken($parent, $schoolA);

    withApiToken($token)->{$method.'Json'}($uri)
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
})->with('cross-school api routes');
