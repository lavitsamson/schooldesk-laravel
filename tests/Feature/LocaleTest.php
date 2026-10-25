<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\School;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

function localeOf(TestResponse $response): string
{
    $locale = '';
    $response->assertInertia(function (AssertableInertia $page) use (&$locale): void {
        $locale = $page->toArray()['props']['locale'];
    });

    return $locale;
}

test('a user without an own language gets the school language', function (): void {
    $school = School::factory()->create(['locale' => Locale::German]);
    $user = memberOf($school, attributes: ['locale' => null]);

    expect(localeOf(actingInSchool($user, $school)->get('/profile')))->toBe('de');
});

test('the user\'s own language wins over the school language', function (): void {
    $school = School::factory()->create(['locale' => Locale::German]);
    $user = memberOf($school, attributes: ['locale' => 'hi']);

    expect(localeOf(actingInSchool($user, $school)->get('/profile')))->toBe('hi');
});

test('guests get their preferred supported browser language', function (): void {
    $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')->get('/login');

    expect(localeOf($response))->toBe('de');

    $response = $this->withHeader('Accept-Language', 'fr-FR')->get('/login');

    expect(localeOf($response))->toBe('en');
});

test('users can choose their language or go back to the school language', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => 'hi',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBe('hi')
        ->and($user->preferredLocale())->toBe('hi');

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => null,
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBeNull();
});

test('only supported languages can be chosen', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => 'fr',
    ])->assertSessionHasErrors('locale');
});
