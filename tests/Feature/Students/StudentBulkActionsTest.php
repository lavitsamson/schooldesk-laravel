<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;

beforeEach(function (): void {
    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->target = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 7', 'section' => 'B']);
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->bulk = fn (array $input) => actingInSchool($this->admin, $this->school)->post(route('students.bulk'), $input);

    $this->enrolled = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for($this->class, 'schoolClass')->for($this->enrolled)->create(['roll_no' => 4]);
    $this->unenrolled = Student::factory()->for($this->school)->create();
});

it('moves and enrols students into a class of this year', function (): void {
    ($this->bulk)([
        'action' => 'move_to_class',
        'student_ids' => [$this->enrolled->id, $this->unenrolled->id],
        'class_id' => $this->target->id,
    ])->assertSessionHas('status', '2 students moved to Grade 7 B.');

    inSchool($this->school, function (): void {
        $enrollments = Enrollment::query()->orderBy('roll_no')->get();

        expect($enrollments)->toHaveCount(2)
            ->and($enrollments->pluck('class_id')->unique()->all())->toBe([$this->target->id])
            ->and($enrollments->pluck('roll_no')->all())->toBe([1, 2]);
    });
});

it('marks students as left', function (): void {
    ($this->bulk)(['action' => 'mark_as_left', 'student_ids' => [$this->enrolled->id]])
        ->assertSessionHas('status', '1 student marked as left.');

    inSchool($this->school, function (): void {
        expect($this->enrolled->fresh()?->status)->toBe(StudentStatus::Left)
            ->and($this->unenrolled->fresh()?->status)->toBe(StudentStatus::Active)
            ->and(Enrollment::query()->sole()->left_on)->not->toBeNull();
    });
});

it('rejects the whole request if any student belongs to another school', function (): void {
    $foreign = Student::factory()->create();

    ($this->bulk)(['action' => 'mark_as_left', 'student_ids' => [$this->enrolled->id, $foreign->id]])
        ->assertSessionHasErrors('student_ids');

    expect(inSchool($this->school, fn () => $this->enrolled->fresh()?->status))->toBe(StudentStatus::Active)
        ->and(Student::query()->withoutGlobalScopes()->find($foreign->id)?->status)->toBe(StudentStatus::Active);
});

it('validates the target class', function (array $input): void {
    ($this->bulk)(['action' => 'move_to_class', 'student_ids' => [$this->enrolled->id], ...$input])
        ->assertSessionHasErrors('class_id');
})->with([
    'missing' => [['class_id' => null]],
    'another school' => [fn () => ['class_id' => SchoolClass::factory()->create()->id]],
    'another year' => [fn () => ['class_id' => SchoolClass::factory()->for($this->school)
        ->for(AcademicYear::factory()->next()->for($this->school))->create()->id]],
]);

it('limits a bulk action to 500 students', function (): void {
    ($this->bulk)(['action' => 'mark_as_left', 'student_ids' => range(1, 501)])->assertSessionHasErrors('student_ids');
});

it('is only for school admins', function (): void {
    actingInSchool(memberOf($this->school, Role::Teacher), $this->school)
        ->post(route('students.bulk'), ['action' => 'mark_as_left', 'student_ids' => [$this->enrolled->id]])
        ->assertForbidden();
});
