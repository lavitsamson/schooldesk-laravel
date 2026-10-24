<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Notification::fake();
    $this->school = School::factory()->completedSetup()->create();
    $this->admin = memberOf($this->school, Role::SchoolAdmin, ['name' => 'Aaron Admin']);
});

describe('listing', function (): void {
    it('shows the school\'s members to admins', function (): void {
        memberOf($this->school, Role::Teacher, ['name' => 'Tina Teacher']);
        memberOf(School::factory()->create(), Role::Teacher, ['name' => 'Elsewhere Ed']);

        actingInSchool($this->admin, $this->school)->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Index')
                ->has('members', 2)
                ->where('members.1.name', 'Tina Teacher')
                ->where('members.1.roles', ['Teacher']));
    });

    it('is forbidden for teachers and parents', function (Role $role): void {
        $user = memberOf($this->school, $role);

        actingInSchool($user, $this->school)->get(route('members.index'))->assertForbidden();
    })->with([Role::Teacher, Role::Parent]);
});

describe('inviting', function (): void {
    it('invites a new person and emails a signed link', function (): void {
        actingInSchool($this->admin, $this->school)
            ->post(route('members.store'), ['name' => 'New Teacher', 'email' => 'New@Example.com', 'role' => 'teacher'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Invitation sent.');

        $user = User::query()->where('email', 'new@example.com')->sole();
        $membership = Membership::query()->where('user_id', $user->id)->sole();

        expect($user->hasPassword())->toBeFalse()
            ->and($membership->school_id)->toBe($this->school->id)
            ->and($membership->status)->toBe(MembershipStatus::Invited)
            ->and(app(CurrentSchool::class)->run($this->school, fn () => $user->fresh()?->hasRole(Role::Teacher)))->toBeTrue();

        Notification::assertSentTo($user, SchoolInvitation::class, function (SchoolInvitation $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, 'signature=') && str_contains($url, 'expires=');
        });
    });

    it('invites an existing user of another school without touching their account', function (): void {
        $other = School::factory()->create();
        $existing = memberOf($other, Role::Teacher, ['email' => 'teach@example.com']);

        actingInSchool($this->admin, $this->school)
            ->post(route('members.store'), ['name' => 'Ignored', 'email' => 'teach@example.com', 'role' => 'parent'])
            ->assertSessionHasNoErrors();

        expect($existing->fresh()?->name)->not->toBe('Ignored')
            ->and($existing->fresh()?->hasPassword())->toBeTrue()
            ->and(Membership::query()->where('user_id', $existing->id)->count())->toBe(2);
    });

    it('rejects inviting someone who is already a member', function (): void {
        $teacher = memberOf($this->school, Role::Teacher);

        actingInSchool($this->admin, $this->school)
            ->post(route('members.store'), ['name' => 'X', 'email' => $teacher->email, 'role' => 'teacher'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    });

    it('validates the input', function (array $input, string $field): void {
        actingInSchool($this->admin, $this->school)
            ->post(route('members.store'), [...['name' => 'A', 'email' => 'a@example.com', 'role' => 'teacher'], ...$input])
            ->assertSessionHasErrors($field);
    })->with([
        'missing name' => [['name' => ''], 'name'],
        'invalid email' => [['email' => 'not-an-email'], 'email'],
        'unknown role' => [['role' => 'platform_admin'], 'role'],
    ]);

    it('is forbidden for teachers', function (): void {
        $teacher = memberOf($this->school, Role::Teacher);

        actingInSchool($teacher, $this->school)
            ->post(route('members.store'), ['name' => 'A', 'email' => 'a@example.com', 'role' => 'school_admin'])
            ->assertForbidden();
    });
});

describe('resending and removing', function (): void {
    it('resends a pending invitation with a new timestamp', function (): void {
        $membership = Membership::factory()->for($this->school)->invited()->create(['invited_at' => now()->subDay()]);

        actingInSchool($this->admin, $this->school)->post(route('members.resend', $membership))->assertRedirect();

        expect($membership->fresh()?->invited_at?->isToday())->toBeTrue();
        Notification::assertSentTo($membership->user, SchoolInvitation::class);
    });

    it('cannot resend to an active member', function (): void {
        $membership = Membership::factory()->for($this->school)->create();

        actingInSchool($this->admin, $this->school)->post(route('members.resend', $membership))->assertForbidden();
    });

    it('removes a member and their roles in this school only', function (): void {
        $other = School::factory()->create();
        $teacher = memberOf($this->school, Role::Teacher);
        Membership::factory()->for($other)->for($teacher)->create();
        app(GrantRole::class)->handle($teacher, $other, Role::Teacher);

        $membership = Membership::query()->where('school_id', $this->school->id)->where('user_id', $teacher->id)->sole();

        actingInSchool($this->admin, $this->school)->delete(route('members.destroy', $membership))->assertRedirect();

        $context = app(CurrentSchool::class);
        expect(Membership::query()->find($membership->id))->toBeNull()
            ->and($context->run($this->school, fn () => $teacher->fresh()?->getRoleNames()->all()))->toBe([])
            ->and($context->run($other, fn () => $teacher->fresh()?->getRoleNames()->all()))->toBe(['teacher']);
    });

    it('does not let admins remove themselves', function (): void {
        $own = Membership::query()->where('user_id', $this->admin->id)->sole();

        actingInSchool($this->admin, $this->school)->delete(route('members.destroy', $own))->assertForbidden();
    });
});
