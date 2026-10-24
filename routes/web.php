<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InvitationController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\SchoolSelectionController;
use App\Http\Controllers\Web\SetupWizardController;
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
