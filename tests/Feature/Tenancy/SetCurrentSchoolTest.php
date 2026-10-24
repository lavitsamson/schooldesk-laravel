<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('selects the only school automatically', function (): void {
    $school = School::factory()->completedSetup()->create();
    $user = memberOf($school, Role::Teacher);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSessionHas(SetCurrentSchool::SESSION_KEY, $school->id)
        ->assertInertia(fn (Assert $page) => $page->where('school.id', $school->id));
});

it('asks users with several schools to choose one', function (): void {
    [$a, $b] = School::factory()->completedSetup()->count(2)->create();
    $user = memberOf($a, Role::Teacher);
    Membership::factory()->for($b)->for($user)->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('schools.select'));

    $this->actingAs($user)->get(route('schools.select'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Schools/Select')
            ->has('schools', 2));
});

it('switches to another school the user belongs to', function (): void {
    [$a, $b] = School::factory()->completedSetup()->count(2)->create();
    $user = memberOf($a, Role::Teacher);
    Membership::factory()->for($b)->for($user)->create();

    actingInSchool($user, $a)->post(route('schools.switch', $b))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(SetCurrentSchool::SESSION_KEY, $b->id);
});

it('ignores a session school the user is not an active member of', function (): void {
    $mine = School::factory()->completedSetup()->create();
    $other = School::factory()->completedSetup()->create();
    $user = memberOf($mine, Role::Teacher);

    actingInSchool($user, $other)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('school.id', $mine->id));
});

it('gives no access through a pending invitation', function (): void {
    $school = School::factory()->completedSetup()->create();
    $user = User::factory()->create();
    Membership::factory()->for($school)->for($user)->invited()->create();

    actingInSchool($user, $school)->get('/dashboard')->assertRedirect(route('schools.select'));
});

it('scopes roles to the school: an admin of one school is nobody in another', function (): void {
    [$a, $b] = School::factory()->completedSetup()->count(2)->create();
    $user = memberOf($a, Role::SchoolAdmin);
    Membership::factory()->for($b)->for($user)->create();
    app(GrantRole::class)->handle($user, $b, Role::Parent);

    actingInSchool($user, $a)->get('/members')->assertOk();
    actingInSchool($user, $b)->get('/members')->assertForbidden();
});

it('shares only the explicit user shape with the frontend', function (): void {
    $school = School::factory()->completedSetup()->create();
    $user = memberOf($school);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->where('school.permissions', fn ($permissions) => collect($permissions)->contains('members.manage')));
});
