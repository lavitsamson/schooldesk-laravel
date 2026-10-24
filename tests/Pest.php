<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * An active member of the school with the given role.
 *
 * @param  array<string, mixed>  $attributes
 */
function memberOf(School $school, Role $role = Role::SchoolAdmin, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    Membership::factory()->for($school)->for($user)->create();
    app(GrantRole::class)->handle($user, $school, $role);

    return $user;
}

/**
 * Act as the user, with the school selected in the session as the school switcher would.
 */
function actingInSchool(User $user, School $school): TestCase
{
    /** @var TestCase $test */
    $test = test();

    return $test->actingAs($user)->withSession([SetCurrentSchool::SESSION_KEY => $school->id]);
}
