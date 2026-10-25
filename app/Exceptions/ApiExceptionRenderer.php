<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Every error of the parent API in one shape (ADR 0009):
 *
 *     { "message": "…", "code": "not_found", "errors": { "month": ["…"] } }
 *
 * `code` is stable for clients to switch on; `errors` only comes with validation_failed.
 * Unexpected errors say nothing about their cause outside debug mode, where Laravel's own
 * output (with the trace) is kept.
 */
final class ApiExceptionRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond(422, $e->getMessage(), ['errors' => $e->errors()]),
            $e instanceof AuthenticationException => $this->respond(401, __('Unauthenticated. Send a valid token: Authorization: Bearer <token>.')),
            $e instanceof HttpExceptionInterface => $this->respond(
                $e->getStatusCode(),
                // A 404 never names the model ("No query results for model [App\Models\…]").
                $e->getMessage() !== '' && $e->getStatusCode() !== 404 ? $e->getMessage() : $this->defaultMessage($e->getStatusCode()),
                headers: $e->getHeaders(),
            ),
            (bool) config('app.debug') => null,
            default => $this->respond(500, __('Server error.')),
        };
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'code' => self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'error'),
            ...$extra,
        ], $status, $headers);
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => __('This action is not allowed.'),
            404 => __('Not found.'),
            429 => __('Too many requests. Please slow down.'),
            default => __('Error.'),
        };
    }
}
