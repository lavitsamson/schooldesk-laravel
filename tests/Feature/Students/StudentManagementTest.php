<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->as = fn () => actingInSchool($this->admin, $this->school);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function studentInput(array $overrides = []): array
{
    return [
        'admission_no' => 'RIS-0100',
        'first_name' => 'Mila',
        'last_name' => 'Okafor',
        'date_of_birth' => '2014-03-09',
        'gender' => 'female',
        'address' => 'Mainkai 1, 60311 Frankfurt',
        ...$overrides,
    ];
}

describe('creating', function (): void {
    it('shows the form with this year\'s classes', function (): void {
        SchoolClass::factory()->for($this->school)->for(AcademicYear::factory()->next()->for($this->school))->create();

        ($this->as)()->get(route('students.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Students/Create')
                ->where('classes', [['id' => $this->class->id, 'name' => 'Grade 7 A']]));
    });

    it('adds a student, enrols them and stores the photo privately', function (): void {
        ($this->as)()->post(route('students.store'), studentInput([
            'class_id' => $this->class->id,
            'photo' => UploadedFile::fake()->image('mila.jpg', 300, 300),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        inSchool($this->school, function (): void {
            $student = Student::query()->sole();
            $enrollment = Enrollment::query()->sole();

            expect($student->fullName())->toBe('Mila Okafor')
                ->and($student->status)->toBe(StudentStatus::Active)
                ->and($enrollment->class_id)->toBe($this->class->id)
                ->and($enrollment->roll_no)->toBe(1)
                ->and($student->photo_path)->toStartWith("schools/{$this->school->id}/students/{$student->id}/");

            Storage::disk('local')->assertExists((string) $student->photo_path);
        });
    });

    it('validates the student', function (array $input, string $field): void {
        ($this->as)()->post(route('students.store'), studentInput($input))->assertSessionHasErrors($field);
    })->with([
        'missing last name' => [['last_name' => ''], 'last_name'],
        'born in the future' => [['date_of_birth' => '2099-01-01'], 'date_of_birth'],
        'unknown gender' => [['gender' => 'robot'], 'gender'],
        'odd admission number' => [['admission_no' => 'RIS 01; DROP'], 'admission_no'],
        'not an image' => [fn () => ['photo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], 'photo'],
        'status on create' => [['status' => 'left'], 'status'],
        'class of another school' => [fn () => ['class_id' => SchoolClass::factory()->create()->id], 'class_id'],
        'class of another year' => [fn () => [
            'class_id' => SchoolClass::factory()->for($this->school)->for(AcademicYear::factory()->next()->for($this->school))->create()->id,
        ], 'class_id'],
    ]);

    it('never reuses an admission number, not even a deleted student\'s', function (): void {
        Student::factory()->for($this->school)->create(['admission_no' => 'RIS-0100'])->delete();

        ($this->as)()->post(route('students.store'), studentInput())->assertSessionHasErrors('admission_no');
    });

    it('allows an admission number another school uses', function (): void {
        Student::factory()->for(School::factory())->create(['admission_no' => 'RIS-0100']);

        ($this->as)()->post(route('students.store'), studentInput())->assertSessionHasNoErrors();
    });
});

describe('viewing and editing', function (): void {
    beforeEach(function (): void {
        $this->student = Student::factory()->for($this->school)->create(['first_name' => 'Mila', 'last_name' => 'Okafor']);
        Enrollment::factory()->for($this->class, 'schoolClass')->for($this->student)->create();
    });

    it('shows the profile with enrolment history', function (): void {
        ($this->as)()->get(route('students.show', $this->student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Students/Show')
                ->where('student.name', 'Mila Okafor')
                ->where('student.class.name', 'Grade 7 A')
                ->has('enrollments', 1)
                ->where('guardians', []));
    });

    it('updates the record', function (): void {
        ($this->as)()->put(route('students.update', $this->student), studentInput(['status' => 'active', 'first_name' => 'Milla']))
            ->assertRedirect(route('students.show', $this->student));

        expect(inSchool($this->school, fn () => $this->student->fresh()?->first_name))->toBe('Milla');
    });

    it('closes the enrolment when a student leaves and reopens it when they return', function (): void {
        ($this->as)()->put(route('students.update', $this->student), studentInput(['status' => 'left']))->assertSessionHasNoErrors();

        inSchool($this->school, function (): void {
            expect($this->student->fresh()?->status)->toBe(StudentStatus::Left)
                ->and(Enrollment::query()->sole()->left_on?->isToday())->toBeTrue();
        });

        ($this->as)()->put(route('students.update', $this->student), studentInput(['status' => 'active']))->assertSessionHasNoErrors();

        expect(inSchool($this->school, fn () => Enrollment::query()->sole()->left_on))->toBeNull();
    });

    it('does not change the class through the edit form', function (): void {
        ($this->as)()->put(route('students.update', $this->student), studentInput(['status' => 'active', 'class_id' => $this->class->id]))
            ->assertSessionHasErrors('class_id');
    });

    it('replaces and removes the photo', function (): void {
        ($this->as)()->put(route('students.update', $this->student), studentInput([
            'status' => 'active', 'photo' => UploadedFile::fake()->image('a.png'),
        ]));
        $first = (string) inSchool($this->school, fn () => $this->student->fresh()?->photo_path);

        ($this->as)()->put(route('students.update', $this->student), studentInput([
            'status' => 'active', 'photo' => UploadedFile::fake()->image('b.png'),
        ]));
        $second = (string) inSchool($this->school, fn () => $this->student->fresh()?->photo_path);

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        ($this->as)()->put(route('students.update', $this->student), studentInput(['status' => 'active', 'remove_photo' => true]));

        Storage::disk('local')->assertMissing($second);
        expect(inSchool($this->school, fn () => $this->student->fresh()?->photo_path))->toBeNull();
    });

    it('serves the photo only through the authorised route', function (): void {
        ($this->as)()->get(route('students.photo', $this->student))->assertNotFound();

        $path = UploadedFile::fake()->image('p.jpg')->store("schools/{$this->school->id}/students/{$this->student->id}", 'local');
        inSchool($this->school, fn () => $this->student->update(['photo_path' => $path]));

        ($this->as)()->get(route('students.photo', $this->student))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, private');

        actingInSchool(memberOf($this->school, Role::Parent), $this->school)
            ->get(route('students.photo', $this->student))
            ->assertForbidden();
    });

    it('soft-deletes a student created by mistake', function (): void {
        ($this->as)()->delete(route('students.destroy', $this->student))->assertRedirect(route('students.index'));

        expect(inSchool($this->school, fn () => Student::query()->withTrashed()->sole()->trashed()))->toBeTrue();
        ($this->as)()->get(route('students.show', $this->student))->assertNotFound();
    });

    it('is only for school admins', function (Role $role): void {
        $user = memberOf($this->school, $role);

        actingInSchool($user, $this->school)->get(route('students.show', $this->student))->assertForbidden();
        actingInSchool($user, $this->school)->get(route('students.create'))->assertForbidden();
        actingInSchool($user, $this->school)->post(route('students.store'), studentInput())->assertForbidden();
        actingInSchool($user, $this->school)->delete(route('students.destroy', $this->student))->assertForbidden();
    })->with([Role::Teacher, Role::Parent]);
});
