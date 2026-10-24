<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\School;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->school, , $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
});

it('lists the school\'s subjects', function (): void {
    Subject::factory()->for($this->school)->create(['name' => 'Art', 'code' => 'ART']);
    Subject::factory()->for(School::factory())->create(['name' => 'Elsewhere']);

    ($this->as)()->get(route('subjects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Subjects/Index')
            ->has('subjects', 1)
            ->where('subjects.0', ['id' => inSchool($this->school, fn () => Subject::query()->sole()->id), 'name' => 'Art', 'code' => 'ART', 'classes_count' => 0]));
});

it('adds a subject with an upper-case code', function (): void {
    ($this->as)()->post(route('subjects.store'), ['name' => 'Physics', 'code' => ' ph '])->assertSessionHasNoErrors();

    expect(inSchool($this->school, fn () => Subject::query()->sole()->code))->toBe('PH');
});

it('rejects a code that is already used, whatever the case', function (): void {
    Subject::factory()->for($this->school)->create(['code' => 'MATH']);

    ($this->as)()->post(route('subjects.store'), ['name' => 'Maths', 'code' => 'math'])->assertSessionHasErrors('code');
});

it('allows a code that another school uses', function (): void {
    Subject::factory()->for(School::factory())->create(['code' => 'MATH']);

    ($this->as)()->post(route('subjects.store'), ['name' => 'Maths', 'code' => 'MATH'])->assertSessionHasNoErrors();
});

it('renames a subject and keeps its own code', function (): void {
    $subject = Subject::factory()->for($this->school)->create(['code' => 'BIO']);

    ($this->as)()->put(route('subjects.update', $subject), ['name' => 'Biology', 'code' => 'BIO'])->assertSessionHasNoErrors();

    expect(inSchool($this->school, fn () => $subject->fresh()?->name))->toBe('Biology');
});

it('deletes a subject with its teaching assignments', function (): void {
    $subject = Subject::factory()->for($this->school)->create();
    $teacher = memberOf($this->school, Role::Teacher);
    TeachingAssignment::query()->create([
        'school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
    ]);

    ($this->as)()->delete(route('subjects.destroy', $subject))->assertRedirect();

    inSchool($this->school, function (): void {
        expect(Subject::query()->count())->toBe(0)
            ->and(TeachingAssignment::query()->count())->toBe(0);
    });
});

it('is only for school admins', function (): void {
    $teacher = memberOf($this->school, Role::Teacher);

    actingInSchool($teacher, $this->school)->get(route('subjects.index'))->assertForbidden();
    actingInSchool($teacher, $this->school)->post(route('subjects.store'), ['name' => 'X', 'code' => 'X'])->assertForbidden();
});
