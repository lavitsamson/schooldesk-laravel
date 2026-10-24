<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->school, $this->year, $this->seven] = setUpSchool();
    $this->eight = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A']);
    $this->next = AcademicYear::factory()->next()->for($this->school)->create();
    $this->nextEight = SchoolClass::factory()->for($this->school)->for($this->next)->create(['name' => 'Grade 8', 'section' => 'A']);
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);

    $enrol = function (SchoolClass $class, string $lastName): Student {
        $student = Student::factory()->for($this->school)->create(['last_name' => $lastName]);
        Enrollment::factory()->for($class, 'schoolClass')->for($student)->create();

        return $student;
    };
    $this->zed = $enrol($this->seven, 'Zed');
    $this->amy = $enrol($this->seven, 'Amy');
    $this->repeater = $enrol($this->seven, 'Repeat');
    $this->leaver = $enrol($this->eight, 'Leaver');
});

/**
 * @return list<array{from_class_id: int, target: int|string|null}>
 */
function promotionMappings(object $test): array
{
    return [
        ['from_class_id' => $test->seven->id, 'target' => $test->nextEight->id],
        ['from_class_id' => $test->eight->id, 'target' => 'graduate'],
    ];
}

it('previews each class with a suggested target and its students', function (): void {
    ($this->as)()->get(route('promotions.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Promotions/Create')
            ->where('toYearId', $this->next->id)
            ->where('preview.source_classes.0.name', 'Grade 7 A')
            ->where('preview.source_classes.0.suggested_target', $this->nextEight->id)
            ->where('preview.source_classes.1.suggested_target', 'graduate')
            ->where('preview.source_classes.0.students.0.name', $this->amy->fullName())
            ->where('preview.source_classes.0.students.0.already_enrolled', false)
            ->has('preview.target_classes', 1));
});

it('promotes, graduates and skips excluded students', function (): void {
    ($this->as)()->post(route('promotions.store'), [
        'to_year_id' => $this->next->id,
        'mappings' => promotionMappings($this),
        'excluded_student_ids' => [$this->repeater->id],
    ])->assertRedirect(route('academic-years.index'))
        ->assertSessionHas('status', '2 promoted to 2027/28, 1 graduated, 1 not promoted. Make 2027/28 the current year when it starts.');

    inSchool($this->school, function (): void {
        $promoted = Enrollment::query()->where('academic_year_id', $this->next->id)->orderBy('roll_no')->get();

        expect($promoted->pluck('student_id')->all())->toBe([$this->amy->id, $this->zed->id])
            ->and($promoted->pluck('roll_no')->all())->toBe([1, 2])
            ->and($promoted->first()?->enrolled_on->toDateString())->toBe('2027-08-01')
            ->and($this->leaver->fresh()?->status)->toBe(StudentStatus::Graduated)
            ->and(Enrollment::query()->where('student_id', $this->leaver->id)->sole()->left_on?->toDateString())->toBe('2027-07-31')
            ->and(Enrollment::query()->where('student_id', $this->repeater->id)->count())->toBe(1)
            // This year's records are untouched: promotion adds next year's enrolments.
            ->and(Enrollment::query()->where('academic_year_id', $this->year->id)->count())->toBe(4)
            ->and(AcademicYear::query()->current()->sole()->id)->toBe($this->year->id);
    });
});

it('changes nothing when run a second time', function (): void {
    $input = ['to_year_id' => $this->next->id, 'mappings' => promotionMappings($this)];

    ($this->as)()->post(route('promotions.store'), $input);
    ($this->as)()->post(route('promotions.store'), $input)
        ->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, '0 promoted to 2027/28, 0 graduated'));

    expect(inSchool($this->school, fn () => Enrollment::query()->where('academic_year_id', $this->next->id)->count()))->toBe(3);
});

it('validates the target year and the classes', function (array $input, string $field): void {
    ($this->as)()->post(route('promotions.store'), $input)->assertSessionHasErrors($field);

    expect(inSchool($this->school, fn () => Enrollment::query()->where('academic_year_id', $this->next->id)->count()))->toBe(0);
})->with([
    'the current year' => [fn () => ['to_year_id' => $this->year->id, 'mappings' => promotionMappings($this)], 'to_year_id'],
    'another school\'s year' => [fn () => ['to_year_id' => AcademicYear::factory()->next()->create()->id, 'mappings' => promotionMappings($this)], 'to_year_id'],
    'a source class of next year' => [fn () => [
        'to_year_id' => $this->next->id, 'mappings' => [['from_class_id' => $this->nextEight->id, 'target' => 'graduate']],
    ], 'mappings.0.from_class_id'],
    'a target class of this year' => [fn () => [
        'to_year_id' => $this->next->id, 'mappings' => [['from_class_id' => $this->seven->id, 'target' => $this->eight->id]],
    ], 'mappings.0.target'],
]);

it('is only for school admins', function (): void {
    $teacher = memberOf($this->school, Role::Teacher);

    actingInSchool($teacher, $this->school)->get(route('promotions.create'))->assertForbidden();
    actingInSchool($teacher, $this->school)->post(route('promotions.store'), [
        'to_year_id' => $this->next->id, 'mappings' => promotionMappings($this),
    ])->assertForbidden();
});
