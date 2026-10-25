<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 10:00:00');
    foreach (setUpFamily() as $key => $value) {
        $this->{$key} = $value;
    }
});

function requestToken(array $body): TestResponse
{
    return test()->postJson(route('api.v1.auth.token.store'), [
        'password' => 'password',
        'device_name' => 'Pixel 9',
        ...$body,
    ]);
}

it('issues a parent a token for their school', function (): void {
    $response = requestToken(['email' => $this->parent->email])
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.school_id', $this->school->id)
        ->assertJsonPath('data.expires_at', '2027-01-18T10:00:00+00:00');

    $token = PersonalAccessToken::query()->sole();
    expect($response->json('data.token'))->toStartWith($token->id.'|sd_')
        ->and($token->school_id)->toBe($this->school->id)
        ->and($token->name)->toBe('Pixel 9')
        ->and($token->abilities)->toBe(['parent'])
        // Only the hash is stored.
        ->and($token->token)->not->toContain(explode('|', $response->json('data.token'))[1]);

    withApiToken($response->json('data.token'))->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.user.email', $this->parent->email)
        ->assertJsonPath('data.school.id', $this->school->id)
        ->assertJsonPath('data.school.currency', 'EUR');
});

it('refuses wrong credentials with one answer', function (array $body): void {
    requestToken($body)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
})->with([
    'wrong password' => fn () => ['email' => $this->parent->email, 'password' => 'wrong-password'],
    'unknown email' => fn () => ['email' => 'nobody@example.com'],
    'no password yet' => fn () => ['email' => User::factory()->invited()->create()->email],
]);

it('validates the request', function (): void {
    $this->postJson(route('api.v1.auth.token.store'), ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('issues tokens to parents only', function (): void {
    requestToken(['email' => $this->teacher->email])
        ->assertForbidden()
        ->assertJson(['message' => 'The API is for parents. Please use the web app.', 'code' => 'forbidden']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('asks parents at several schools to choose one', function (): void {
    [$other] = setUpSchool();
    Membership::factory()->for($other)->for($this->parent)->create();
    app(GrantRole::class)->handle($this->parent, $other, Role::Parent);
    [$notTheirs] = setUpSchool();

    requestToken(['email' => $this->parent->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('school_id');
    requestToken(['email' => $this->parent->email, 'school_id' => $notTheirs->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.school_id.0', 'You are not a member of this school.');

    requestToken(['email' => $this->parent->email, 'school_id' => $other->id])
        ->assertCreated()
        ->assertJsonPath('data.school_id', $other->id);
});

it('limits sign-in attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        requestToken(['email' => $this->parent->email, 'password' => 'wrong-password'])->assertUnprocessable();
    }

    requestToken(['email' => $this->parent->email])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('revokes the token on sign-out', function (): void {
    $token = apiToken($this->parent, $this->school);

    withApiToken($token)->deleteJson(route('api.v1.auth.token.destroy'))->assertNoContent();

    expect(PersonalAccessToken::query()->count())->toBe(0);
    withApiToken($token)->getJson(route('api.v1.me'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('refuses missing, invalid and expired tokens', function (): void {
    $token = apiToken($this->parent, $this->school);

    $this->getJson(route('api.v1.me'))->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    withApiToken('1|not-a-real-token')->getJson(route('api.v1.me'))->assertUnauthorized();

    $this->travel(91)->days();
    withApiToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
});

it('stops working when the membership ends', function (): void {
    $token = apiToken($this->parent, $this->school);
    Membership::query()->where('user_id', $this->parent->id)->update(['status' => MembershipStatus::Invited]);

    withApiToken($token)->getJson(route('api.v1.me'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your membership of this token\'s school has ended.');
});

it('does not accept a web session', function (): void {
    actingInSchool($this->parent, $this->school)->getJson(route('api.v1.me'))->assertUnauthorized();
});
