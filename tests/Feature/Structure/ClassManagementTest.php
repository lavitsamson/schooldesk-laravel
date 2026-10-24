<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->teacher = memberOf($this->school, Role::Teacher, ['name' => 'Tina Teacher']);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
});

describe('listing', function (): void {
    it('shows the current year\'s classes with their teachers', function (): void {
        $this->class->update(['class_teacher_id' => $this->teacher->id]);
        memberOf($this->school, Role::Parent, ['name' => 'Pat Parent']);
        memberOf(School::factory()->create(), Role::Teacher, ['name' => 'Elsewhere Ed']);

        ($this->as)()->get(route('classes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Classes/Index')
                ->where('yearId', $this->year->id)
                ->has('classes', 1)
                ->where('classes.0.display_name', 'Grade 7 A')
                ->where('classes.0.class_teacher.name', 'Tina Teacher')
                // Only this school's teachers are offered: no parents, no other schools.
                ->where('teachers', [['id' => $this->teacher->id, 'name' => 'Tina Teacher']]));
    });

    it('shows another year\'s classes when asked', function (): void {
        $next = AcademicYear::factory()->next()->for($this->school)->create();
        SchoolClass::factory()->for($this->school)->for($next)->count(2)->create();

        ($this->as)()->get(route('classes.index', ['year_id' => $next->id]))
            ->assertInertia(fn (Assert $page) => $page->where('yearId', $next->id)->has('classes', 2));
    });

    it('is only for school admins', function (): void {
        actingInSchool($this->teacher, $this->school)->get(route('classes.index'))->assertForbidden();
        actingInSchool($this->teacher, $this->school)->get(route('classes.show', $this->class))->assertForbidden();
    });
});

describe('saving', function (): void {
    it('adds a class with a class teacher', function (): void {
        ($this->as)()->post(route('classes.store'), [
            'academic_year_id' => $this->year->id, 'name' => 'Grade 8', 'section' => 'B', 'class_teacher_id' => $this->teacher->id,
        ])->assertSessionHas('status', 'Class Grade 8 B added.');

        inSchool($this->school, fn () => expect(SchoolClass::query()->where('name', 'Grade 8')->sole()->class_teacher_id)
            ->toBe($this->teacher->id));
    });

    it('validates a class', function (array $input, string $field): void {
        ($this->as)()->post(route('classes.store'), [
            'academic_year_id' => $this->year->id, 'name' => 'Grade 9', 'section' => null, ...$input,
        ])->assertSessionHasErrors($field);
    })->with([
        'duplicate in the year' => [['name' => 'Grade 7', 'section' => 'A'], 'name'],
        'year of another school' => [fn () => ['academic_year_id' => AcademicYear::factory()->create()->id], 'academic_year_id'],
        'teacher of another school' => [fn () => ['class_teacher_id' => memberOf(School::factory()->create(), Role::Teacher)->id], 'class_teacher_id'],
        'a parent as class teacher' => [fn () => ['class_teacher_id' => memberOf($this->school, Role::Parent)->id], 'class_teacher_id'],
    ]);

    it('updates a class but never moves it to another year', function (): void {
        ($this->as)()->put(route('classes.update', $this->class), ['name' => 'Grade 7', 'section' => 'C'])
            ->assertSessionHasNoErrors();
        expect(inSchool($this->school, fn () => $this->class->fresh()?->section))->toBe('C');

        ($this->as)()->put(route('classes.update', $this->class), [
            'name' => 'Grade 7', 'academic_year_id' => AcademicYear::factory()->next()->for($this->school)->create()->id,
        ])->assertSessionHasErrors('academic_year_id');
    });

    it('shows a class with its subjects and students', function (): void {
        $math = Subject::factory()->for($this->school)->create(['name' => 'Mathematics']);
        TeachingAssignment::query()->create([
            'school_id' => $this->school->id, 'class_id' => $this->class->id, 'subject_id' => $math->id, 'teacher_id' => $this->teacher->id,
        ]);
        Enrollment::factory()->for($this->class, 'schoolClass')->count(2)->create();

        ($this->as)()->get(route('classes.show', $this->class))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Classes/Show')
                ->where('subjects.0.teacher_id', $this->teacher->id)
                ->has('students', 2));
    });
});

describe('subject teachers', function (): void {
    it('assigns, changes and removes subject teachers', function (): void {
        $math = Subject::factory()->for($this->school)->create();
        $art = Subject::factory()->for($this->school)->create();
        $other = memberOf($this->school, Role::Teacher);

        ($this->as)()->put(route('classes.assignments', $this->class), ['assignments' => [
            ['subject_id' => $math->id, 'teacher_id' => $this->teacher->id],
            ['subject_id' => $art->id, 'teacher_id' => $other->id],
        ]])->assertSessionHasNoErrors();

        ($this->as)()->put(route('classes.assignments', $this->class), ['assignments' => [
            ['subject_id' => $math->id, 'teacher_id' => $other->id],
            ['subject_id' => $art->id, 'teacher_id' => null],
        ]])->assertSessionHasNoErrors();

        inSchool($this->school, fn () => expect(TeachingAssignment::query()->pluck('teacher_id', 'subject_id')->all())
            ->toBe([$math->id => $other->id]));
    });

    it('rejects another school\'s subjects and teachers', function (): void {
        $foreignSubject = Subject::factory()->create();
        $foreignTeacher = memberOf(School::factory()->create(), Role::Teacher);
        $math = Subject::factory()->for($this->school)->create();

        ($this->as)()->put(route('classes.assignments', $this->class), ['assignments' => [
            ['subject_id' => $foreignSubject->id, 'teacher_id' => $this->teacher->id],
            ['subject_id' => $math->id, 'teacher_id' => $foreignTeacher->id],
        ]])->assertSessionHasErrors(['assignments.0.subject_id', 'assignments.1.teacher_id']);
    });
});

describe('deleting', function (): void {
    it('deletes an empty class', function (): void {
        ($this->as)()->delete(route('classes.destroy', $this->class))
            ->assertRedirect(route('classes.index', ['year_id' => $this->year->id]));

        expect(inSchool($this->school, fn () => SchoolClass::query()->count()))->toBe(0);
    });

    it('keeps a class that has students', function (): void {
        Enrollment::factory()->for($this->class, 'schoolClass')->create();

        ($this->as)()->delete(route('classes.destroy', $this->class))->assertSessionHasErrors('class');

        expect(inSchool($this->school, fn () => SchoolClass::query()->count()))->toBe(1);
    });
});
