<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\AcademicYear;
use App\Models\Membership;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CurrentSchool;
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

/**
 * Runs the callback with the school as the current school, e.g. to query school-owned
 * models in assertions (they fail closed without a school context).
 *
 * @template T
 *
 * @param  callable(School): T  $callback
 * @return T
 */
function inSchool(School $school, callable $callback): mixed
{
    return app(CurrentSchool::class)->run($school, $callback);
}

/**
 * A set-up school with a current academic year (2026/27) and one class, Grade 7 A.
 *
 * @return array{0: School, 1: AcademicYear, 2: SchoolClass}
 */
function setUpSchool(): array
{
    $school = School::factory()->completedSetup()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $class = SchoolClass::factory()->for($school)->for($year)->create(['name' => 'Grade 7', 'section' => 'A']);

    return [$school, $year, $class];
}
