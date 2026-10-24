<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Term;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function nextYearInput(array $overrides = []): array
{
    return [
        'name' => '2027/28',
        'starts_on' => '2027-08-01',
        'ends_on' => '2028-07-31',
        'terms' => [
            ['name' => 'Autumn', 'starts_on' => '2027-08-01', 'ends_on' => '2027-12-17'],
            ['name' => 'Spring', 'starts_on' => '2028-01-03', 'ends_on' => '2028-07-31'],
        ],
        ...$overrides,
    ];
}

it('lists the school\'s years with their terms', function (): void {
    Term::factory()->for($this->year)->create();
    AcademicYear::factory()->for(School::factory())->create(['name' => '1999/00']);

    ($this->as)()->get(route('academic-years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AcademicYears/Index')
            ->has('years', 1)
            ->where('years.0.name', '2026/27')
            ->where('years.0.is_current', true)
            ->has('years.0.terms', 1)
            ->where('years.0.can.delete', false)
            ->where('canPromote', false));
});

it('offers promotion once a later year exists', function (): void {
    AcademicYear::factory()->next()->for($this->school)->create();

    ($this->as)()->get(route('academic-years.index'))
        ->assertInertia(fn (Assert $page) => $page->has('years', 2)->where('canPromote', true));
});

it('is only for school admins', function (Role $role): void {
    actingInSchool(memberOf($this->school, $role), $this->school)
        ->get(route('academic-years.index'))
        ->assertForbidden();
})->with([Role::Teacher, Role::Parent]);

it('adds next year with its terms and a copy of this year\'s classes', function (): void {
    ($this->as)()->post(route('academic-years.store'), nextYearInput(['copy_classes_from' => $this->year->id]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Academic year 2027/28 added.');

    inSchool($this->school, function (): void {
        $next = AcademicYear::query()->where('name', '2027/28')->sole();

        expect($next->is_current)->toBeFalse()
            ->and($next->terms()->pluck('name')->all())->toBe(['Autumn', 'Spring'])
            ->and(SchoolClass::query()->where('academic_year_id', $next->id)->pluck('name')->all())->toBe(['Grade 7']);
    });
});

it('validates a new year', function (array $input, string $field): void {
    ($this->as)()->post(route('academic-years.store'), nextYearInput($input))->assertSessionHasErrors($field);
})->with([
    'name already used' => [['name' => '2026/27'], 'name'],
    'ends before it starts' => [['ends_on' => '2027-01-01'], 'ends_on'],
    'overlapping terms' => [['terms' => [
        ['name' => 'A', 'starts_on' => '2027-08-01', 'ends_on' => '2027-12-31'],
        ['name' => 'B', 'starts_on' => '2027-12-01', 'ends_on' => '2028-07-31'],
    ]], 'terms.1.starts_on'],
    'term outside the year' => [['terms' => [['name' => 'A', 'starts_on' => '2027-08-01', 'ends_on' => '2028-09-01']]], 'terms.0.ends_on'],
]);

it('does not copy classes from another school\'s year', function (): void {
    $foreign = AcademicYear::factory()->for(School::factory())->create();

    ($this->as)()->post(route('academic-years.store'), nextYearInput(['copy_classes_from' => $foreign->id]))
        ->assertSessionHasErrors('copy_classes_from');
});

it('updates a year and keeps existing terms by id', function (): void {
    $autumn = Term::factory()->for($this->year)->create(['name' => 'Autumn', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18']);
    $spring = Term::factory()->for($this->year)->create(['name' => 'Spring', 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26']);

    ($this->as)()->put(route('academic-years.update', $this->year), [
        'name' => '2026/27',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'terms' => [
            ['id' => $autumn->id, 'name' => 'Michaelmas', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18'],
            ['id' => null, 'name' => 'Summer', 'starts_on' => '2027-04-12', 'ends_on' => '2027-07-31'],
        ],
    ])->assertSessionHasNoErrors();

    inSchool($this->school, function () use ($autumn, $spring): void {
        expect(Term::query()->find($autumn->id)?->name)->toBe('Michaelmas')
            ->and(Term::query()->find($spring->id))->toBeNull()
            ->and(Term::query()->where('academic_year_id', $this->year->id)->count())->toBe(2);
    });
});

it('rejects a term id of another year', function (): void {
    $other = AcademicYear::factory()->next()->for($this->school)->create();
    $foreignTerm = Term::factory()->for($other)->create();

    ($this->as)()->put(route('academic-years.update', $this->year), [
        'name' => '2026/27', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'terms' => [['id' => $foreignTerm->id, 'name' => 'Hijack', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18']],
    ])->assertSessionHasErrors('terms.0.id');
});

it('makes another year the current one', function (): void {
    $next = AcademicYear::factory()->next()->for($this->school)->create();

    ($this->as)()->post(route('academic-years.make-current', $next))->assertRedirect();

    inSchool($this->school, fn () => expect(AcademicYear::query()->current()->sole()->id)->toBe($next->id));
});

it('deletes an unused year but never the current one or one with students', function (): void {
    $next = AcademicYear::factory()->next()->for($this->school)->create();

    ($this->as)()->delete(route('academic-years.destroy', $this->year))->assertForbidden();

    $used = SchoolClass::factory()->for($this->school)->for($next)->create();
    Enrollment::factory()->for($used, 'schoolClass')->create();
    ($this->as)()->delete(route('academic-years.destroy', $next))->assertSessionHasErrors('year');

    inSchool($this->school, fn () => expect(AcademicYear::query()->count())->toBe(2));
});

it('deletes a year that has no students, with its classes', function (): void {
    $next = AcademicYear::factory()->next()->for($this->school)->create();
    SchoolClass::factory()->for($this->school)->for($next)->create();

    ($this->as)()->delete(route('academic-years.destroy', $next))->assertSessionHasNoErrors();

    inSchool($this->school, function () use ($next): void {
        expect(AcademicYear::query()->find($next->id))->toBeNull()
            ->and(SchoolClass::query()->where('academic_year_id', $next->id)->count())->toBe(0);
    });
});
