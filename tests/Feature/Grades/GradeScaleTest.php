<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\GradeScale;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    foreach (setUpGradebook() as $key => $value) {
        $this->{$key} = $value;
    }
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->bands = fn (): array => inSchool($this->school, fn () => GradeScale::query()->orderByDesc('min_percent')->get(['label', 'min_percent', 'max_percent'])->toArray());
});

it('shows the school\'s grade scale and the presets', function (): void {
    actingInSchool($this->admin, $this->school)
        ->get(route('grade-scale.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('GradeScale/Edit')
            ->has('bands', 6)
            ->where('bands.0', ['label' => 'A', 'min_percent' => '90.00', 'max_percent' => '100.00'])
            ->has('presets', 2));
});

it('saves an adjusted scale', function (): void {
    actingInSchool($this->admin, $this->school)->put(route('grade-scale.update'), ['bands' => [
        ['label' => 'Fail', 'min_percent' => '0', 'max_percent' => '39.99'],
        ['label' => ' Merit ', 'min_percent' => '60', 'max_percent' => '84.99'],
        ['label' => 'Pass', 'min_percent' => '40', 'max_percent' => '59.99'],
        ['label' => 'Distinction', 'min_percent' => '85', 'max_percent' => '100'],
    ]])->assertSessionHasNoErrors()->assertSessionHas('status', 'Grade scale saved.');

    expect(($this->bands)())->toBe([
        ['label' => 'Distinction', 'min_percent' => '85.00', 'max_percent' => '100.00'],
        ['label' => 'Merit', 'min_percent' => '60.00', 'max_percent' => '84.99'],
        ['label' => 'Pass', 'min_percent' => '40.00', 'max_percent' => '59.99'],
        ['label' => 'Fail', 'min_percent' => '0.00', 'max_percent' => '39.99'],
    ]);
});

it('rejects scales that leave a gap or overlap', function (array $bands, string $error): void {
    $before = ($this->bands)();

    actingInSchool($this->admin, $this->school)
        ->put(route('grade-scale.update'), ['bands' => $bands])
        ->assertSessionHasErrors([$error]);

    expect(($this->bands)())->toBe($before);
})->with([
    'gap' => [[
        ['label' => 'Pass', 'min_percent' => '50', 'max_percent' => '100'],
        ['label' => 'Fail', 'min_percent' => '0', 'max_percent' => '45'],
    ], 'bands'],
    'overlap' => [[
        ['label' => 'Pass', 'min_percent' => '50', 'max_percent' => '100'],
        ['label' => 'Fail', 'min_percent' => '0', 'max_percent' => '60'],
    ], 'bands'],
    'one band' => [[['label' => 'All', 'min_percent' => '0', 'max_percent' => '100']], 'bands'],
    'same label' => [[
        ['label' => 'Pass', 'min_percent' => '50', 'max_percent' => '100'],
        ['label' => 'pass', 'min_percent' => '0', 'max_percent' => '49.99'],
    ], 'bands.1.label'],
    'above 100' => [[
        ['label' => 'Pass', 'min_percent' => '50', 'max_percent' => '101'],
        ['label' => 'Fail', 'min_percent' => '0', 'max_percent' => '49.99'],
    ], 'bands.0.max_percent'],
]);

it('is for school admins only', function (): void {
    actingInSchool($this->teacher, $this->school)->get(route('grade-scale.edit'))->assertForbidden();
    actingInSchool($this->teacher, $this->school)->put(route('grade-scale.update'), ['bands' => []])->assertForbidden();
});
