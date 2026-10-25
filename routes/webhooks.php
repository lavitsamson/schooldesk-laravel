<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Server-to-server callbacks. No web middleware: no session, cookies or CSRF token. Each
 * endpoint authenticates the sender itself (Stripe: the signature over the raw body).
 */
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.stripe');
