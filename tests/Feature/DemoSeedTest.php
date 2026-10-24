<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\CurrentSchool;
use Database\Seeders\DemoSchoolsSeeder;

it('seeds two set-up demo schools with working logins', function (): void {
    $this->seed(DemoSchoolsSeeder::class);

    expect(School::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Gymnasium am Main', 'Riverside International School']);

    $riverside = School::query()->where('slug', 'riverside')->sole();
    expect($riverside->isSetUp())->toBeTrue();

    app(CurrentSchool::class)->run($riverside, function (): void {
        $tom = User::query()->where('email', 'teacher@riverside.demo')->sole();
        $paula = Guardian::query()->where('email', 'parent@riverside.demo')->sole();

        expect(Subject::query()->count())->toBe(10)
            ->and($tom->hasRole(Role::Teacher))->toBeTrue()
            ->and(TeachingAssignment::query()->where('teacher_id', $tom->id)->count())->toBeGreaterThan(0)
            ->and(Student::query()->count())->toBe(384)
            // Every student has a class this year and exactly one primary guardian.
            ->and(Enrollment::query()->count())->toBe(384)
            ->and(GuardianStudent::query()->whereIn('student_id', Student::query()->select('id'))->where('is_primary', true)->count())->toBe(384)
            ->and($paula->user_id)->toBe(User::query()->where('email', 'parent@riverside.demo')->value('id'))
            ->and($paula->students()->pluck('first_name')->sort()->values()->all())->toBe(['Lotta', 'Pia']);
    });

    $gymnasium = School::query()->where('slug', 'gymnasium-am-main')->sole();
    expect(app(CurrentSchool::class)->run($gymnasium, fn () => Student::query()->count()))->toBe(120);

    $this->post('/login', ['email' => 'admin@riverside.demo', 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get('/dashboard')->assertOk();
});
