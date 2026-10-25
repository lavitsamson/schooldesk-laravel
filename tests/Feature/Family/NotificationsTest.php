<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Notifications\StudentAbsent;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A stored notification, as the database channel would write it.
 *
 * @param  array<string, mixed>  $data
 */
function storedNotification(User $user, ?School $school, array $data = [], ?string $readAt = null): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $school?->id,
        'type' => 'student-absent',
        'data' => [...['student_id' => 1, 'first_name' => 'Ada', 'class' => 'Grade 7 A', 'date' => '2026-10-02'], ...$data],
        'read_at' => $readAt,
    ]);
}

beforeEach(function (): void {
    $this->travelTo('2026-10-20 10:00:00');
    foreach (setUpFamily() as $key => $value) {
        $this->{$key} = $value;
    }
});

it('remembers the school a notification was sent in', function (): void {
    $record = AttendanceRecord::factory()->for($this->students[0])->for($this->class, 'schoolClass')->absent()->create(['date' => '2026-10-02']);

    inSchool($this->school, fn () => $this->parent->notifyNow(new StudentAbsent($record), ['database']));

    expect($this->parent->notifications()->sole())
        ->school_id->toBe($this->school->id)
        ->type->toBe('student-absent')
        ->data->toMatchArray(['first_name' => 'Ada', 'date' => '2026-10-02']);
});

it('lists the current school\'s notifications with readable titles', function (): void {
    [$otherSchool] = setUpSchool();
    Membership::factory()->for($otherSchool)->for($this->parent)->create();
    app(GrantRole::class)->handle($this->parent, $otherSchool, Role::Parent);

    storedNotification($this->parent, $this->school);
    storedNotification($this->parent, $otherSchool, ['first_name' => 'Elsewhere']);
    storedNotification($this->parent, $this->school, readAt: '2026-10-03 10:00:00');

    actingInSchool($this->parent, $this->school)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 2)
            ->where('notifications.data.0.title', 'Ada was absent on 2 October 2026.')
            ->where('notifications.data.0.subject', ['type' => 'student', 'id' => 1, 'student_id' => 1])
            ->where('unreadNotifications', 1));
});

it('marks one or all notifications as read', function (): void {
    $first = storedNotification($this->parent, $this->school);
    $second = storedNotification($this->parent, $this->school);

    actingInSchool($this->parent, $this->school)->post(route('notifications.read', $first->id))->assertRedirect();
    expect($first->fresh()->read_at)->not->toBeNull()
        ->and($second->fresh()->read_at)->toBeNull();

    actingInSchool($this->parent, $this->school)->post(route('notifications.read-all'))->assertRedirect();
    expect($second->fresh()->read_at)->not->toBeNull();
});

it('does not find other users\' or other schools\' notifications', function (): void {
    [$otherSchool] = setUpSchool();
    $someoneElses = storedNotification($this->classTeacher, $this->school);
    $otherSchools = storedNotification($this->parent, $otherSchool);

    foreach ([$someoneElses, $otherSchools] as $notification) {
        actingInSchool($this->parent, $this->school)->post(route('notifications.read', $notification->id))->assertNotFound();
        expect($notification->fresh()->read_at)->toBeNull();
    }
});
