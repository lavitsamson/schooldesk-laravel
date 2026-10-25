<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Fees\HandleStripeWebhook;
use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe's webhook endpoint. Outside the web middleware (no session, no CSRF token): what
 * proves the request comes from Stripe is the signature over the raw body, checked before
 * anything is read from it. A 400 makes Stripe retry later; 200 tells it to stop.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeService $stripe, HandleStripeWebhook $handle): JsonResponse
    {
        try {
            $event = $stripe->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Rejected a Stripe webhook', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        return response()->json(['status' => $handle->handle($event)]);
    }
}
