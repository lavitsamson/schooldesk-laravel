<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportCardController;
use Illuminate\Support\Facades\Route;

/*
 * The parent API (ADR 0009), for a mobile app: Sanctum bearer tokens, each for one school.
 * SetCurrentSchool (api group) takes the school from the token; `school` refuses tokens whose
 * membership has ended. Docs: /docs (Scribe).
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token.store');

    Route::middleware(['auth:sanctum', 'school', 'throttle:api'])->group(function (): void {
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy'])->name('auth.token.destroy');
        Route::get('/me', MeController::class)->name('me');

        Route::controller(ChildController::class)->group(function (): void {
            Route::get('/children', 'index')->name('children.index');
            Route::get('/children/{student}/attendance', 'attendance')->name('children.attendance');
            Route::get('/children/{student}/grades', 'grades')->name('children.grades');
            Route::get('/children/{student}/report-cards', 'reportCards')->name('children.report-cards');
        });
        Route::get('/report-cards/{reportCard}/pdf', [ReportCardController::class, 'pdf'])->name('report-cards.pdf');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/{invoice}/checkout-session', [InvoiceController::class, 'checkoutSession'])
            ->middleware('throttle:checkout')
            ->name('invoices.checkout-session');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    });
});
