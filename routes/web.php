<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AcademicYearController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GuardianController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\PromotionController;
use App\Http\Controllers\Web\SchoolClassController;
use App\Http\Controllers\Web\SchoolSelectionController;
use App\Http\Controllers\Web\SetupWizardController;
use App\Http\Controllers\Web\StudentBulkActionController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentGuardianController;
use App\Http\Controllers\Web\StudentPhotoController;
use App\Http\Controllers\Web\SubjectController;
use App\Models\Membership;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/schools', [SchoolSelectionController::class, 'index'])->name('schools.select');
    Route::post('/schools/{school}/switch', [SchoolSelectionController::class, 'switch'])->name('schools.switch');

    // Everything below acts for the current school.
    Route::middleware('school')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::controller(SetupWizardController::class)->prefix('setup')->group(function (): void {
            Route::get('/', 'start')->name('setup');
            Route::get('/{step}', 'show')->name('setup.show');
            Route::put('/school', 'updateSchool')->name('setup.school');
            Route::put('/academic-year', 'saveAcademicYear')->name('setup.academic-year');
            Route::put('/classes', 'syncClasses')->name('setup.classes');
            Route::put('/subjects', 'syncSubjects')->name('setup.subjects');
            Route::put('/grade-scale', 'applyGradeScale')->name('setup.grade-scale');
        });

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->middleware('throttle:invitations')->name('members.store');
        Route::post('/members/{membership}/resend', [MemberController::class, 'resend'])->middleware('throttle:invitations')->name('members.resend');
        Route::delete('/members/{membership}', [MemberController::class, 'destroy'])->name('members.destroy');

        // School structure
        Route::controller(AcademicYearController::class)->group(function (): void {
            Route::get('/academic-years', 'index')->name('academic-years.index');
            Route::post('/academic-years', 'store')->name('academic-years.store');
            Route::put('/academic-years/{academicYear}', 'update')->name('academic-years.update');
            Route::post('/academic-years/{academicYear}/make-current', 'makeCurrent')->name('academic-years.make-current');
            Route::delete('/academic-years/{academicYear}', 'destroy')->name('academic-years.destroy');
        });

        Route::controller(SchoolClassController::class)->group(function (): void {
            Route::get('/classes', 'index')->name('classes.index');
            Route::post('/classes', 'store')->name('classes.store');
            Route::get('/classes/{schoolClass}', 'show')->name('classes.show');
            Route::put('/classes/{schoolClass}', 'update')->name('classes.update');
            Route::put('/classes/{schoolClass}/assignments', 'updateAssignments')->name('classes.assignments');
            Route::delete('/classes/{schoolClass}', 'destroy')->name('classes.destroy');
        });

        Route::controller(SubjectController::class)->group(function (): void {
            Route::get('/subjects', 'index')->name('subjects.index');
            Route::post('/subjects', 'store')->name('subjects.store');
            Route::put('/subjects/{subject}', 'update')->name('subjects.update');
            Route::delete('/subjects/{subject}', 'destroy')->name('subjects.destroy');
        });

        // Students and guardians
        Route::post('/students/bulk', StudentBulkActionController::class)->name('students.bulk');
        Route::get('/students/{student}/photo', StudentPhotoController::class)->name('students.photo');
        Route::controller(StudentController::class)->group(function (): void {
            Route::get('/students', 'index')->name('students.index');
            Route::get('/students/create', 'create')->name('students.create');
            Route::post('/students', 'store')->name('students.store');
            Route::get('/students/{student}', 'show')->name('students.show');
            Route::get('/students/{student}/edit', 'edit')->name('students.edit');
            Route::put('/students/{student}', 'update')->name('students.update');
            Route::delete('/students/{student}', 'destroy')->name('students.destroy');
        });

        Route::controller(StudentGuardianController::class)->group(function (): void {
            Route::post('/students/{student}/guardians', 'store')->name('students.guardians.store');
            Route::put('/students/{student}/guardians/{guardian}/primary', 'update')->name('students.guardians.update');
            Route::delete('/students/{student}/guardians/{guardian}', 'destroy')->name('students.guardians.destroy');
        });

        Route::controller(GuardianController::class)->group(function (): void {
            Route::get('/guardians', 'index')->name('guardians.index');
            Route::put('/guardians/{guardian}', 'update')->name('guardians.update');
            Route::delete('/guardians/{guardian}', 'destroy')->name('guardians.destroy');
            Route::post('/guardians/{guardian}/invite', 'invite')->middleware('throttle:invitations')->name('guardians.invite');
        });

        Route::controller(ImportController::class)->group(function (): void {
            Route::get('/imports', 'index')->name('imports.index');
            Route::get('/imports/template', 'template')->name('imports.template');
            Route::post('/imports', 'store')->middleware('throttle:imports')->name('imports.store');
            Route::get('/imports/{importBatch}', 'show')->name('imports.show');
            Route::get('/imports/{importBatch}/error-report', 'errorReport')->name('imports.error-report');
        });

        Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
        Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    });
});

// Invitation links are signed and expiring; they work for guests and logged-in users alike.
Route::bind('invitation', fn (string $id): Membership => Membership::query()->findOrFail($id));
Route::get('/invitations/{invitation}', [InvitationController::class, 'show'])
    ->middleware('signed')
    ->name('invitations.show');
Route::post('/invitations/{invitation}', [InvitationController::class, 'accept'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('invitations.accept');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
