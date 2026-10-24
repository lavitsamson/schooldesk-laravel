<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;

test('registration screen can be rendered', function (): void {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users register a school, become its admin and start the setup wizard', function (): void {
    $response = $this->post('/register', [
        'school_name' => 'Riverside International School',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('setup', absolute: false));

    $user = User::query()->where('email', 'test@example.com')->sole();
    $school = School::query()->sole();
    $membership = $user->memberships()->sole();

    expect($school->name)->toBe('Riverside International School')
        ->and($school->slug)->toBe('riverside-international-school')
        ->and($school->isSetUp())->toBeFalse()
        ->and($membership->school_id)->toBe($school->id)
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and(app(CurrentSchool::class)->run($school, fn () => $user->hasRole(Role::SchoolAdmin)))->toBeTrue();
});

test('a school name is required', function (): void {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('school_name');

    $this->assertGuest();
});

test('school slugs stay unique', function (): void {
    School::factory()->create(['slug' => 'riverside']);

    $this->post('/register', [
        'school_name' => 'Riverside',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(School::query()->where('slug', 'like', 'riverside-%')->count())->toBe(1);
});
