<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\School;
use App\Models\Subject;
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
        expect(Subject::query()->count())->toBe(10)
            ->and(User::query()->where('email', 'teacher@riverside.demo')->sole()->hasRole(Role::Teacher))->toBeTrue();
    });

    $this->post('/login', ['email' => 'admin@riverside.demo', 'password' => DemoSchoolsSeeder::PASSWORD])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get('/dashboard')->assertOk();
});
