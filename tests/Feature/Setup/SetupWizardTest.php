<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\GradeScale;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Support\CurrentSchool;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->school = School::factory()->create();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
    $this->inSchool = fn (Closure $callback): mixed => app(CurrentSchool::class)->run($this->school, $callback);
});

function academicYearInput(array $overrides = []): array
{
    return [
        'name' => '2026/27',
        'starts_on' => '2026-08-01',
        'ends_on' => '2027-07-31',
        'terms' => [
            ['name' => 'Autumn', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-18'],
            ['name' => 'Spring', 'starts_on' => '2027-01-04', 'ends_on' => '2027-03-26'],
            ['name' => 'Summer', 'starts_on' => '2027-04-12', 'ends_on' => '2027-07-31'],
        ],
        ...$overrides,
    ];
}

it('sends a school admin of a new school to the wizard', function (): void {
    ($this->as)()->get('/dashboard')->assertRedirect(route('setup'));
    ($this->as)()->get(route('setup'))->assertRedirect(route('setup.show', 'school'));
});

it('renders every step', function (string $step, string $component): void {
    ($this->as)()->get(route('setup.show', $step))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->has('steps', 5)->where('current', $step)
            // The layout reads the shared school prop (permissions); no step may replace it.
            ->where('school.id', $this->school->id)
            ->has('school.permissions'));
})->with([
    ['school', 'Setup/SchoolDetails'],
    ['academic-year', 'Setup/AcademicYear'],
    ['classes', 'Setup/Classes'],
    ['subjects', 'Setup/Subjects'],
    ['grade-scale', 'Setup/GradeScale'],
]);

it('returns 404 for an unknown step', function (): void {
    ($this->as)()->get('/setup/payments')->assertNotFound();
});

it('saves school details', function (): void {
    ($this->as)()->put(route('setup.school'), [
        'name' => 'Gymnasium am Main', 'country' => 'DE', 'timezone' => 'Europe/Berlin', 'locale' => 'de', 'currency' => 'EUR',
    ])->assertRedirect(route('setup.show', 'academic-year'));

    expect($this->school->fresh()?->only(['name', 'timezone']))
        ->toBe(['name' => 'Gymnasium am Main', 'timezone' => 'Europe/Berlin']);
});

it('validates school details', function (array $input, string $field): void {
    ($this->as)()->put(route('setup.school'), [
        ...['name' => 'X', 'country' => 'DE', 'timezone' => 'Europe/Berlin', 'locale' => 'en', 'currency' => 'EUR'],
        ...$input,
    ])->assertSessionHasErrors($field);
})->with([
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'unsupported locale' => [['locale' => 'fr'], 'locale'],
    'lowercase country' => [['country' => 'de'], 'country'],
    'unsupported currency' => [['currency' => 'JPY'], 'currency'],
]);

it('saves the academic year and replaces terms when saved again', function (): void {
    ($this->as)()->put(route('setup.academic-year'), academicYearInput())->assertRedirect(route('setup.show', 'classes'));
    ($this->as)()->put(route('setup.academic-year'), academicYearInput([
        'terms' => [['name' => 'Whole year', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']],
    ]));

    ($this->inSchool)(function (): void {
        expect(AcademicYear::query()->count())->toBe(1)
            ->and(AcademicYear::query()->sole()->is_current)->toBeTrue()
            ->and(Term::query()->pluck('name')->all())->toBe(['Whole year']);
    });
});

it('rejects overlapping or out-of-range terms', function (array $terms, string $field): void {
    ($this->as)()->put(route('setup.academic-year'), academicYearInput(['terms' => $terms]))
        ->assertSessionHasErrors($field);
})->with([
    'overlapping' => [[
        ['name' => 'A', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-31'],
        ['name' => 'B', 'starts_on' => '2026-12-01', 'ends_on' => '2027-03-31'],
    ], 'terms.1.starts_on'],
    'before the year' => [[['name' => 'A', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31']], 'terms.0.starts_on'],
    'after the year' => [[['name' => 'A', 'starts_on' => '2026-08-01', 'ends_on' => '2027-08-31']], 'terms.0.ends_on'],
    'ends before it starts' => [[['name' => 'A', 'starts_on' => '2026-10-01', 'ends_on' => '2026-09-01']], 'terms.0.ends_on'],
]);

it('needs an academic year before classes', function (): void {
    ($this->as)()->put(route('setup.classes'), ['classes' => [['name' => 'Grade 7', 'section' => 'A']]])
        ->assertSessionHasErrors('classes');
});

it('syncs classes by name and section', function (): void {
    ($this->as)()->put(route('setup.academic-year'), academicYearInput());

    ($this->as)()->put(route('setup.classes'), ['classes' => [
        ['name' => 'Grade 7', 'section' => 'A'], ['name' => 'Grade 7', 'section' => 'B'], ['name' => 'Grade 8', 'section' => ''],
    ]])->assertRedirect(route('setup.show', 'subjects'));
    ($this->as)()->put(route('setup.classes'), ['classes' => [
        ['name' => 'Grade 7', 'section' => 'A'], ['name' => 'Grade 8', 'section' => null],
    ]]);

    ($this->inSchool)(function (): void {
        expect(SchoolClass::query()->orderBy('name')->orderBy('section')->get()->map->displayName()->all())
            ->toBe(['Grade 7 A', 'Grade 8']);
    });
});

it('rejects duplicate classes', function (): void {
    ($this->as)()->put(route('setup.academic-year'), academicYearInput());

    ($this->as)()->put(route('setup.classes'), ['classes' => [
        ['name' => 'Grade 7', 'section' => 'A'], ['name' => 'grade 7', 'section' => 'a'],
    ]])->assertSessionHasErrors('classes.1.name');
});

it('syncs subjects by code', function (): void {
    ($this->as)()->put(route('setup.subjects'), ['subjects' => [
        ['name' => 'Maths', 'code' => 'math'], ['name' => 'Art', 'code' => 'ART'],
    ]])->assertRedirect(route('setup.show', 'grade-scale'));
    ($this->as)()->put(route('setup.subjects'), ['subjects' => [['name' => 'Mathematics', 'code' => 'MATH']]]);

    ($this->inSchool)(function (): void {
        expect(Subject::query()->get(['name', 'code'])->toArray())->toBe([['name' => 'Mathematics', 'code' => 'MATH']]);
    });
});

it('rejects duplicate subject codes', function (): void {
    ($this->as)()->put(route('setup.subjects'), ['subjects' => [
        ['name' => 'Maths', 'code' => 'MATH'], ['name' => 'Mechanics', 'code' => 'math'],
    ]])->assertSessionHasErrors('subjects.1.code');
});

it('only finishes once the earlier steps are done', function (): void {
    ($this->as)()->put(route('setup.grade-scale'), ['preset' => 'a_f'])->assertSessionHasErrors('preset');

    expect($this->school->fresh()?->isSetUp())->toBeFalse();
});

it('applies the grade scale and completes setup', function (): void {
    ($this->as)()->put(route('setup.academic-year'), academicYearInput());
    ($this->as)()->put(route('setup.classes'), ['classes' => [['name' => 'Grade 7', 'section' => 'A']]]);
    ($this->as)()->put(route('setup.subjects'), ['subjects' => [['name' => 'Maths', 'code' => 'MATH']]]);

    ($this->as)()->put(route('setup.grade-scale'), ['preset' => 'german_1_6'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status');

    expect($this->school->fresh()?->isSetUp())->toBeTrue();
    ($this->inSchool)(fn () => expect(GradeScale::query()->orderByDesc('min_percent')->pluck('label')->all())
        ->toBe(['1', '2', '3', '4', '5', '6']));

    ($this->as)()->get(route('setup.show', 'school'))->assertRedirect(route('dashboard'));
    ($this->as)()->put(route('setup.subjects'), ['subjects' => [['name' => 'Art', 'code' => 'ART']]])->assertForbidden();
});

it('is only for school admins', function (Role $role): void {
    $user = memberOf($this->school, $role);

    actingInSchool($user, $this->school)->get(route('setup.show', 'school'))->assertForbidden();
    actingInSchool($user, $this->school)->put(route('setup.subjects'), ['subjects' => [['name' => 'Art', 'code' => 'ART']]])
        ->assertForbidden();
    actingInSchool($user, $this->school)->get('/dashboard')->assertOk();
})->with([Role::Teacher, Role::Parent]);

it('only touches the current school', function (): void {
    $other = School::factory()->create();
    app(CurrentSchool::class)->run($other, fn () => Subject::factory()->for($other)->create(['code' => 'OTHER']));

    ($this->as)()->put(route('setup.subjects'), ['subjects' => [['name' => 'Maths', 'code' => 'MATH']]]);

    app(CurrentSchool::class)->run($other, fn () => expect(Subject::query()->pluck('code')->all())->toBe(['OTHER']));
});
