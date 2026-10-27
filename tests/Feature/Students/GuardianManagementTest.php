<?php

declare(strict_types=1);

use App\Actions\Guardians\LinkGuardian;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Exceptions\CrossSchoolWriteException;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Membership;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Notification::fake();
    [$this->school] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
    $this->student = Student::factory()->for($this->school)->create(['first_name' => 'Mila']);
    $this->primaryOf = fn (Student $student): ?int => GuardianStudent::query()
        ->where('student_id', $student->id)->where('is_primary', true)->value('guardian_id');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newGuardianInput(array $overrides = []): array
{
    return ['mode' => 'new', 'name' => 'Ada Okafor', 'email' => 'Ada@Example.com', 'phone' => '+49 151 1234567', 'relationship' => 'mother', ...$overrides];
}

describe('linking', function (): void {
    it('adds a new guardian, who becomes the primary contact', function (): void {
        ($this->as)()->post(route('students.guardians.store', $this->student), newGuardianInput())
            ->assertSessionHas('status', 'Ada Okafor added as a guardian.');

        inSchool($this->school, function (): void {
            $guardian = Guardian::query()->sole();

            expect($guardian->email)->toBe('ada@example.com')
                ->and(($this->primaryOf)($this->student))->toBe($guardian->id);
        });
    });

    it('keeps the primary contact unless asked to change it', function (): void {
        ($this->as)()->post(route('students.guardians.store', $this->student), newGuardianInput());
        ($this->as)()->post(route('students.guardians.store', $this->student), newGuardianInput(['name' => 'Ben', 'email' => 'ben@example.com']));
        $first = inSchool($this->school, fn () => Guardian::query()->where('name', 'Ada Okafor')->sole());
        expect(($this->primaryOf)($this->student))->toBe($first->id);

        ($this->as)()->post(route('students.guardians.store', $this->student), newGuardianInput(['name' => 'Cy', 'email' => null, 'is_primary' => true]));
        $cy = inSchool($this->school, fn () => Guardian::query()->where('name', 'Cy')->sole());
        expect(($this->primaryOf)($this->student))->toBe($cy->id);
    });

    it('links an existing guardian to a sibling by email', function (): void {
        $guardian = Guardian::factory()->for($this->school)->create(['email' => 'ada@example.com']);
        $sibling = Student::factory()->for($this->school)->create();
        inSchool($this->school, fn () => app(LinkGuardian::class)->handle($sibling, $guardian));

        ($this->as)()->post(route('students.guardians.store', $this->student), ['mode' => 'existing', 'email' => 'ADA@example.com'])
            ->assertSessionHasNoErrors();

        expect(inSchool($this->school, fn () => $guardian->students()->count()))->toBe(2);
    });

    it('validates the guardian', function (array $input, string $field): void {
        Guardian::factory()->for($this->school)->create(['email' => 'taken@example.com']);
        Guardian::factory()->for(School::factory())->create(['email' => 'other-school@example.com']);

        ($this->as)()->post(route('students.guardians.store', $this->student), $input)->assertSessionHasErrors($field);
    })->with([
        'unknown relationship' => [newGuardianInput(['relationship' => 'neighbour']), 'relationship'],
        'invalid phone' => [newGuardianInput(['phone' => 'call me']), 'phone'],
        'new guardian with a known email' => [newGuardianInput(['email' => 'taken@example.com']), 'email'],
        'unknown existing guardian' => [['mode' => 'existing', 'email' => 'nobody@example.com'], 'email'],
        'existing guardian of another school' => [['mode' => 'existing', 'email' => 'other-school@example.com'], 'email'],
    ]);

    it('does not link the same guardian twice', function (): void {
        ($this->as)()->post(route('students.guardians.store', $this->student), newGuardianInput());

        ($this->as)()->post(route('students.guardians.store', $this->student), ['mode' => 'existing', 'email' => 'ada@example.com'])
            ->assertSessionHasErrors('email');
    });

    it('never links a guardian of another school', function (): void {
        $foreign = Guardian::factory()->create();

        inSchool($this->school, fn () => app(LinkGuardian::class)->handle($this->student, $foreign));
    })->throws(CrossSchoolWriteException::class);
});

describe('primary and unlinking', function (): void {
    beforeEach(function (): void {
        $this->ada = Guardian::factory()->for($this->school)->create(['name' => 'Ada']);
        $this->ben = Guardian::factory()->for($this->school)->create(['name' => 'Ben']);
        inSchool($this->school, function (): void {
            app(LinkGuardian::class)->handle($this->student, $this->ada);
            app(LinkGuardian::class)->handle($this->student, $this->ben);
        });
    });

    it('makes another guardian the primary contact', function (): void {
        ($this->as)()->put(route('students.guardians.update', [$this->student, $this->ben]))->assertRedirect();

        expect(($this->primaryOf)($this->student))->toBe($this->ben->id)
            ->and(GuardianStudent::query()->where('student_id', $this->student->id)->where('is_primary', true)->count())->toBe(1);
    });

    it('hands over the primary role when the primary guardian is unlinked', function (): void {
        ($this->as)()->delete(route('students.guardians.destroy', [$this->student, $this->ada]))->assertRedirect();

        expect(($this->primaryOf)($this->student))->toBe($this->ben->id)
            ->and(inSchool($this->school, fn () => Guardian::query()->count()))->toBe(2);
    });

    it('returns 404 for a guardian who is not linked to the student', function (): void {
        $stranger = Guardian::factory()->for($this->school)->create();

        ($this->as)()->put(route('students.guardians.update', [$this->student, $stranger]))->assertNotFound();
        ($this->as)()->delete(route('students.guardians.destroy', [$this->student, $stranger]))->assertNotFound();
    });
});

describe('guardian list', function (): void {
    it('lists and searches guardians with their children', function (): void {
        $ada = Guardian::factory()->for($this->school)->create(['name' => 'Ada Okafor', 'email' => 'ada@example.com']);
        // A fixed email: a random one can contain "ada" and match the search.
        Guardian::factory()->for($this->school)->create(['name' => 'Ben Smith', 'email' => 'ben@example.com']);
        Guardian::factory()->for(School::factory())->create(['name' => 'Ada Elsewhere']);
        inSchool($this->school, fn () => app(LinkGuardian::class)->handle($this->student, $ada));

        ($this->as)()->get(route('guardians.index', ['search' => 'ada']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Guardians/Index')
                ->has('guardians.data', 1)
                ->where('guardians.data.0.students.0.name', $this->student->fullName())
                ->where('guardians.data.0.can.delete', false));
    });

    it('updates a guardian but keeps the email of one with portal access', function (): void {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        $guardian = Guardian::factory()->for($this->school)->create(['email' => 'ada@example.com', 'user_id' => $user->id]);

        ($this->as)()->put(route('guardians.update', $guardian), [
            'name' => 'Ada O.', 'email' => 'new@example.com', 'phone' => null, 'relationship' => 'guardian',
        ])->assertSessionHasNoErrors();

        $guardian = inSchool($this->school, fn () => $guardian->fresh());
        expect($guardian?->name)->toBe('Ada O.')
            ->and($guardian?->email)->toBe('ada@example.com');
    });

    it('deletes only guardians without children', function (): void {
        $linked = Guardian::factory()->for($this->school)->create();
        $unlinked = Guardian::factory()->for($this->school)->create();
        inSchool($this->school, fn () => app(LinkGuardian::class)->handle($this->student, $linked));

        ($this->as)()->delete(route('guardians.destroy', $linked))->assertForbidden();
        ($this->as)()->delete(route('guardians.destroy', $unlinked))->assertRedirect();

        expect(inSchool($this->school, fn () => Guardian::query()->pluck('id')->all()))->toBe([$linked->id]);
    });

    it('is only for school admins', function (): void {
        actingInSchool(memberOf($this->school, Role::Teacher), $this->school)->get(route('guardians.index'))->assertForbidden();
    });
});

describe('parent portal invitations', function (): void {
    it('invites a guardian as a parent and links their account', function (): void {
        $guardian = Guardian::factory()->for($this->school)->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        ($this->as)()->post(route('guardians.invite', $guardian))
            ->assertSessionHas('status', 'Invitation to the parent portal sent to ada@example.com.');

        $user = User::query()->where('email', 'ada@example.com')->sole();
        expect(inSchool($this->school, fn () => $guardian->fresh()?->user_id))->toBe($user->id)
            ->and(Membership::query()->where('user_id', $user->id)->sole()->status)->toBe(MembershipStatus::Invited)
            ->and(inSchool($this->school, fn () => $user->hasRole(Role::Parent)))->toBeTrue();
        Notification::assertSentTo($user, SchoolInvitation::class);
    });

    it('gives an existing member parent access without a new invitation', function (): void {
        $teacher = memberOf($this->school, Role::Teacher, ['email' => 'tina@example.com']);
        $guardian = Guardian::factory()->for($this->school)->create(['email' => 'tina@example.com']);

        ($this->as)()->post(route('guardians.invite', $guardian))->assertSessionHasNoErrors();

        expect(Membership::query()->where('user_id', $teacher->id)->sole()->status)->toBe(MembershipStatus::Active)
            ->and(inSchool($this->school, fn () => $teacher->fresh()?->getRoleNames()->sort()->values()->all()))->toBe(['parent', 'teacher']);
        Notification::assertNothingSent();
    });

    it('cannot invite a guardian without an email or one already invited', function (): void {
        $noEmail = Guardian::factory()->for($this->school)->create(['email' => null]);
        $invited = Guardian::factory()->for($this->school)->create(['user_id' => User::factory()->create()->id]);

        ($this->as)()->post(route('guardians.invite', $noEmail))->assertForbidden();
        ($this->as)()->post(route('guardians.invite', $invited))->assertForbidden();
    });
});
