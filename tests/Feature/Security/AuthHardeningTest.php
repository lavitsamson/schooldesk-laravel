<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

$registration = fn (string $password): array => [
    'school_name' => 'Riverside International School',
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => $password,
    'password_confirmation' => $password,
];

it('needs passwords of at least 12 characters', function () use ($registration): void {
    $this->post('/register', $registration('Short-pass1'))->assertSessionHasErrors('password');
    $this->assertGuest();

    $this->post('/register', $registration('long enough!'))->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

it('answers a reset request the same way whether or not the address has an account', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
    $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);
    // A second request within the minute is throttled by the password broker: still the same answer.
    $again = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

    foreach ([$known, $unknown, $again] as $response) {
        $response->assertRedirect('/forgot-password')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If this email address has an account, we have emailed a link to reset the password.');
    }
    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('limits registrations and password reset requests per IP address', function (string $uri): void {
    for ($i = 0; $i < 6; $i++) {
        $this->post($uri, ['email' => "user{$i}@example.com"])->assertStatus(302);
    }

    $this->post($uri, ['email' => 'user7@example.com'])->assertTooManyRequests();
})->with(['/register', '/forgot-password', '/reset-password']);
