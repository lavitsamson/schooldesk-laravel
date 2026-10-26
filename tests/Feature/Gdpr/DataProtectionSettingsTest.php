<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 09:00:00');
    [$this->school, , $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);

    $this->notes = collect(['2025-10-19', '2025-10-20', '2026-10-01'])->map(fn (string $date) => AttendanceRecord::factory()->absent()
        ->for($this->class, 'schoolClass')
        ->create(['date' => $date, 'note' => "Ill on {$date}"]));
});

it('shows the retention setting to school admins only', function (): void {
    actingInSchool($this->admin, $this->school)->get(route('data-protection.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('DataProtection/Edit')
            ->where('attendanceNoteRetentionMonths', null)
            ->where('retentionOptions', [6, 12, 24, 36])
            ->where('exportLinkHours', 24)
            ->where('anonymisedStudents', 0));

    actingInSchool(memberOf($this->school, Role::Teacher), $this->school)->get(route('data-protection.edit'))->assertForbidden();
});

it('saves the retention period', function (): void {
    actingInSchool($this->admin, $this->school)
        ->put(route('data-protection.update'), ['attendance_note_retention_months' => 12])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Data protection settings saved.');
    expect($this->school->refresh()->attendanceNoteRetentionMonths())->toBe(12);

    actingInSchool($this->admin, $this->school)
        ->put(route('data-protection.update'), ['attendance_note_retention_months' => 7])
        ->assertSessionHasErrors('attendance_note_retention_months');

    actingInSchool($this->admin, $this->school)
        ->put(route('data-protection.update'), ['attendance_note_retention_months' => null])
        ->assertSessionHasNoErrors();
    expect($this->school->refresh()->attendanceNoteRetentionMonths())->toBeNull();

    actingInSchool(memberOf($this->school, Role::Teacher), $this->school)
        ->put(route('data-protection.update'), ['attendance_note_retention_months' => 6])
        ->assertForbidden();
});

it('deletes notes older than each school\'s retention period every night', function (): void {
    $this->school->update(['settings' => [School::SETTING_ATTENDANCE_NOTE_RETENTION_MONTHS => 12]]);

    [$otherSchool, , $otherClass] = setUpSchool();
    $kept = AttendanceRecord::factory()->absent()->for($otherClass, 'schoolClass')->create(['date' => '2020-01-06', 'note' => 'Kept']);

    $this->artisan('gdpr:prune-attendance-notes')->assertSuccessful();

    $notes = inSchool($this->school, fn () => AttendanceRecord::query()->orderBy('date')->get());
    // The records stay; only the old note goes.
    expect($notes->pluck('note')->all())->toBe([null, 'Ill on 2025-10-20', 'Ill on 2026-10-01'])
        ->and(inSchool($otherSchool, fn () => $kept->refresh()->note))->toBe('Kept');
});

it('counts the anonymised students', function (): void {
    Student::factory()->for($this->school)->count(2)->create(['anonymised_at' => now()]);

    actingInSchool($this->admin, $this->school)->get(route('data-protection.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('anonymisedStudents', 2));
});
