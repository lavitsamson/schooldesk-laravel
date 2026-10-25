<?php

declare(strict_types=1);

use App\Enums\FeeFrequency;
use App\Enums\Role;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->as = fn (?User $user = null) => actingInSchool($user ?? $this->admin, $this->school);
});

it('lists the fees of the current year', function (): void {
    FeeStructure::factory()->forClass($this->otherClass)->create(['name' => 'Lab fee', 'amount' => 4_550, 'frequency' => FeeFrequency::Annual]);

    ($this->as)()->get(route('fee-structures.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Fees/Structures')
            ->has('feeStructures', 2)
            ->where('feeStructures.0.name', 'Tuition')
            ->where('feeStructures.0.class', null)
            ->where('feeStructures.0.amount', 120_000)
            ->where('feeStructures.0.amount_input', '1200.00')
            ->where('feeStructures.1.name', 'Lab fee')
            ->where('feeStructures.1.class', 'Grade 8 A')
            ->where('feeStructures.1.frequency_label', 'Once a year')
            ->has('classes', 2));
});

it('adds a fee with the amount typed as a decimal', function (): void {
    ($this->as)()->post(route('fee-structures.store'), [
        'academic_year_id' => $this->year->id,
        'class_id' => $this->class->id,
        'name' => '  Admission fee ',
        'amount' => '1.250,50',
        'frequency' => 'one_off',
        'due_day_offset' => '30',
    ])->assertSessionHasNoErrors();

    $fee = inSchool($this->school, fn () => FeeStructure::query()->where('name', 'Admission fee')->sole());
    expect($fee->amount)->toBe(125_050)
        ->and($fee->class_id)->toBe($this->class->id)
        ->and($fee->frequency)->toBe(FeeFrequency::OneOff)
        ->and($fee->due_day_offset)->toBe(30);
});

it('validates fees', function (array $input, string $field): void {
    ($this->as)()->post(route('fee-structures.store'), [
        'academic_year_id' => $this->year->id,
        'name' => 'Trip',
        'amount' => '100',
        'frequency' => 'term',
        'due_day_offset' => '14',
        ...$input,
    ])->assertSessionHasErrors($field);
})->with([
    'amount not a number' => [['amount' => '12 euros'], 'amount'],
    'amount zero' => [['amount' => '0'], 'amount'],
    'amount negative' => [['amount' => '-5'], 'amount'],
    'amount too large' => [['amount' => '1000000.01'], 'amount'],
    'unknown frequency' => [['frequency' => 'weekly'], 'frequency'],
    'due offset too long' => [['due_day_offset' => '365'], 'due_day_offset'],
    'name missing' => [['name' => ''], 'name'],
]);

it('rejects a class of another year or school', function (): void {
    $otherSchoolClass = SchoolClass::factory()->create();

    ($this->as)()->post(route('fee-structures.store'), [
        'academic_year_id' => $this->year->id,
        'class_id' => $otherSchoolClass->id,
        'name' => 'Trip',
        'amount' => '100',
        'frequency' => 'term',
        'due_day_offset' => '14',
    ])->assertSessionHasErrors('class_id');
});

it('updates and deletes a fee', function (): void {
    ($this->as)()->put(route('fee-structures.update', $this->tuition), [
        'academic_year_id' => $this->year->id,
        'class_id' => null,
        'name' => 'Tuition',
        'amount' => '1300',
        'frequency' => 'term',
        'due_day_offset' => '10',
    ])->assertSessionHasNoErrors();

    expect($this->tuition->refresh()->amount)->toBe(130_000)
        ->and($this->tuition->due_day_offset)->toBe(10);

    ($this->as)()->delete(route('fee-structures.destroy', $this->tuition))->assertSessionHasNoErrors();
    expect(inSchool($this->school, fn () => FeeStructure::query()->count()))->toBe(0);
});

it('is for school admins only', function (Role $role): void {
    $user = memberOf($this->school, $role);

    ($this->as)($user)->get(route('fee-structures.index'))->assertForbidden();
    ($this->as)($user)->post(route('fee-structures.store'), ['name' => 'X'])->assertForbidden();
    ($this->as)($user)->delete(route('fee-structures.destroy', $this->tuition))->assertForbidden();
    ($this->as)($user)->get(route('fees.index'))->assertForbidden();
    ($this->as)($user)->get(route('invoices.index'))->assertForbidden();
})->with([Role::Teacher, Role::Parent]);
