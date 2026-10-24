<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->school = School::factory()->completedSetup()->create(['name' => 'Riverside']);
});

function invite(School $school, User $user, Role $role = Role::Teacher): Membership
{
    $membership = Membership::factory()->for($school)->for($user)->invited()->create();
    app(GrantRole::class)->handle($user, $school, $role);

    return $membership;
}

describe('a new user', function (): void {
    beforeEach(function (): void {
        $this->invitee = User::factory()->invited()->create(['email' => 'new@example.com']);
        $this->membership = invite($this->school, $this->invitee);
        $this->url = SchoolInvitation::url($this->membership);
    });

    it('sees the account form', function (): void {
        $this->get($this->url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invitations/Accept')
                ->where('mode', 'register')
                ->where('schoolName', 'Riverside')
                ->where('roles', ['Teacher']));
    });

    it('sets a password, verifies the email and joins the school', function (): void {
        $this->post($this->url, ['name' => 'Nina New', 'password' => 'DemoPass123!', 'password_confirmation' => 'DemoPass123!'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(SetCurrentSchool::SESSION_KEY, $this->school->id);

        $user = $this->invitee->fresh();
        expect($user?->name)->toBe('Nina New')
            ->and($user?->hasPassword())->toBeTrue()
            ->and($user?->email_verified_at)->not->toBeNull()
            ->and($this->membership->fresh()?->status)->toBe(MembershipStatus::Active);
        $this->assertAuthenticatedAs($this->invitee);
    });

    it('requires a confirmed password', function (): void {
        $this->post($this->url, ['name' => 'Nina', 'password' => 'DemoPass123!', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors('password');

        expect($this->membership->fresh()?->status)->toBe(MembershipStatus::Invited);
    });
});

describe('an existing user', function (): void {
    beforeEach(function (): void {
        $this->invitee = memberOf(School::factory()->create(), Role::Teacher);
        $this->membership = invite($this->school, $this->invitee);
        $this->url = SchoolInvitation::url($this->membership);
    });

    it('is asked to log in first and comes back to the invitation', function (): void {
        $this->get($this->url)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'login'))
            ->assertSessionHas('url.intended', $this->url);
    });

    it('accepts with one click when logged in', function (): void {
        $this->actingAs($this->invitee)->get($this->url)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'accept'));

        $this->actingAs($this->invitee)->post($this->url)->assertRedirect(route('dashboard'));

        expect($this->membership->fresh()?->status)->toBe(MembershipStatus::Active);
    });

    it('cannot be accepted by someone else who is logged in', function (): void {
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get($this->url)
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'wrong_account'));
        $this->actingAs($intruder)->post($this->url)->assertForbidden();

        expect($this->membership->fresh()?->status)->toBe(MembershipStatus::Invited);
    });

    it('cannot be accepted by a guest', function (): void {
        $this->post($this->url)->assertForbidden();
    });
});

describe('the link', function (): void {
    beforeEach(function (): void {
        $this->membership = invite($this->school, User::factory()->invited()->create());
    });

    it('rejects a tampered signature', function (): void {
        $url = SchoolInvitation::url($this->membership);

        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
    });

    it('expires', function (): void {
        $url = SchoolInvitation::url($this->membership);

        $this->travel(8)->days();

        $this->get($url)->assertForbidden();
    });

    it('is replaced by a re-sent invitation', function (): void {
        $old = SchoolInvitation::url($this->membership);
        $this->membership->update(['invited_at' => now()->addMinute()]);

        $this->get($old)->assertForbidden();
        $this->get(SchoolInvitation::url($this->membership->fresh()))->assertOk();
    });

    it('stops working when the invitation is revoked', function (): void {
        $url = SchoolInvitation::url($this->membership);
        $this->membership->delete();

        $this->get($url)->assertNotFound();
    });

    it('cannot be pointed at another invitation', function (): void {
        $other = invite(School::factory()->create(), User::factory()->invited()->create());
        $url = SchoolInvitation::url($this->membership);

        $this->get(str_replace("/invitations/{$this->membership->id}", "/invitations/{$other->id}", $url))
            ->assertForbidden();
    });

    it('is not a plain unsigned route', function (): void {
        $this->get(URL::route('invitations.show', ['invitation' => $this->membership->id]))->assertForbidden();
    });
});
