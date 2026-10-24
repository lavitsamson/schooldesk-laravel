<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->list = fn (array $query = []) => actingInSchool($this->admin, $this->school)->get(route('students.index', $query));
});

/**
 * @param  array<string, mixed>  $attributes
 */
function studentIn(SchoolClass $class, array $attributes = []): Student
{
    $student = Student::factory()->create(['school_id' => $class->school_id, ...$attributes]);
    Enrollment::factory()->for($class, 'schoolClass')->for($student)->create();

    return $student;
}

it('lists the school\'s active students by last name, without personal details', function (): void {
    studentIn($this->class, ['first_name' => 'Zoe', 'last_name' => 'Adams']);
    Student::factory()->for($this->school)->create(['first_name' => 'Ben', 'last_name' => 'Brown']);
    Student::factory()->for($this->school)->left()->create(['last_name' => 'Gone']);
    Student::factory()->for(School::factory())->create(['last_name' => 'Elsewhere']);

    ($this->list)()
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Index')
            ->has('students.data', 2)
            ->where('students.data.0.name', 'Zoe Adams')
            ->where('students.data.0.class.name', 'Grade 7 A')
            ->where('students.data.1.class', null)
            ->missing('students.data.0.date_of_birth')
            ->missing('students.data.0.address')
            ->where('filters.status', 'active')
            ->has('classes', 1));
});

it('paginates on the server', function (): void {
    Student::factory()->for($this->school)->count(30)->create();

    ($this->list)(['page' => 2])
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.data', 5)
            ->where('students.meta.total', 30)
            ->where('students.meta.current_page', 2));
});

it('searches by name, full name and admission number, ignoring case', function (string $search): void {
    Student::factory()->for($this->school)->create(['first_name' => 'Mila', 'last_name' => 'Okafor', 'admission_no' => 'RIS-0042']);
    Student::factory()->for($this->school)->create(['first_name' => 'Noah', 'last_name' => 'Smith', 'admission_no' => 'RIS-0007']);

    ($this->list)(['search' => $search])
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.last_name', 'Okafor'));
})->with(['okaf', 'MILA', 'mila okafor', 'ris-0042']);

it('treats % and _ in a search as plain characters', function (): void {
    Student::factory()->for($this->school)->create(['last_name' => 'Smith']);

    ($this->list)(['search' => '%'])->assertInertia(fn (Assert $page) => $page->has('students.data', 0));
});

it('filters by class and by status', function (): void {
    $other = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8']);
    studentIn($this->class, ['last_name' => 'Seven']);
    studentIn($other, ['last_name' => 'Eight']);
    studentIn($other, ['last_name' => 'Graduate', 'status' => StudentStatus::Graduated]);

    ($this->list)(['class_id' => $other->id])
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.last_name', 'Eight'));

    ($this->list)(['class_id' => $other->id, 'status' => 'all'])
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 2));

    ($this->list)(['status' => 'graduated'])
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.last_name', 'Graduate'));
});

it('sorts by admission number and by class', function (): void {
    $eight = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A']);
    studentIn($eight, ['last_name' => 'Aaron', 'admission_no' => 'A-2']);
    studentIn($this->class, ['last_name' => 'Zed', 'admission_no' => 'A-1']);

    ($this->list)(['sort' => 'admission_no', 'direction' => 'desc'])
        ->assertInertia(fn (Assert $page) => $page->where('students.data.0.admission_no', 'A-2'));

    ($this->list)(['sort' => 'class'])
        ->assertInertia(fn (Assert $page) => $page->where('students.data.0.last_name', 'Zed'));
});

it('rejects unknown sort columns', function (): void {
    ($this->list)(['sort' => 'date_of_birth'])->assertSessionHasErrors('sort');
});

it('is only for school admins', function (Role $role): void {
    actingInSchool(memberOf($this->school, $role), $this->school)->get(route('students.index'))->assertForbidden();
})->with([Role::Teacher, Role::Parent]);
