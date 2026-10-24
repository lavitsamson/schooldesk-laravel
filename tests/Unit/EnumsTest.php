<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\GradeScalePreset;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\SetupStep;

it('covers 0–100 % with contiguous, non-overlapping bands', function (GradeScalePreset $preset): void {
    $bands = array_reverse($preset->bands()); // worst first

    expect($bands[0]['min_percent'])->toBe('0.00')
        ->and(end($bands)['max_percent'])->toBe('100.00');

    foreach (array_slice($bands, 1) as $index => $band) {
        $previousMax = (float) $bands[$index]['max_percent'];

        expect(round((float) $band['min_percent'] - $previousMax, 2))->toBe(0.01);
    }
})->with(GradeScalePreset::cases());

it('uses the German 1–6 labels from best to worst', function (): void {
    expect(array_column(GradeScalePreset::German1To6->bands(), 'label'))
        ->toBe(['1', '2', '3', '4', '5', '6']);
});

it('gives every permission to at least one role', function (): void {
    $granted = array_column(array_merge(...array_map(fn (Role $role): array => $role->permissions(), Role::cases())), 'value');

    expect(array_values(array_diff(array_column(Permission::cases(), 'value'), $granted)))->toBe([]);
});

it('keeps teachers and parents away from school administration', function (Role $role): void {
    expect($role->permissions())
        ->not->toContain(Permission::ManageSchool)
        ->not->toContain(Permission::ManageMembers)
        ->not->toContain(Permission::ManageFees)
        ->not->toContain(Permission::ViewAuditLog);
})->with([Role::Teacher, Role::Parent]);

it('counts present and late students as attended', function (): void {
    expect(array_map(fn (AttendanceStatus $s): bool => $s->countsAsAttended(), AttendanceStatus::cases()))
        ->toBe([true, false, true, false])
        ->and(array_map(fn (AttendanceStatus $s): string => $s->code(), AttendanceStatus::cases()))
        ->toBe(['P', 'A', 'L', 'E']);
});

it('walks the setup steps in order', function (): void {
    expect(SetupStep::SchoolDetails->next())->toBe(SetupStep::AcademicYear)
        ->and(SetupStep::Subjects->next())->toBe(SetupStep::GradeScale)
        ->and(SetupStep::GradeScale->next())->toBeNull();
});
