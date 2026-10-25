<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
 * Sanctum serves the parent API with bearer tokens only (ADR 0009). The web app, including the
 * parent portal, keeps Breeze's session auth, so the API never accepts a session cookie.
 */
return [

    // No SPA cookie mode: no first-party domain gets stateful (session) API requests.
    'stateful' => [],

    // No session guard: a request to /api is authenticated by its bearer token or not at all.
    'guard' => [],

    // Each token gets its own expiry when issued (schooldesk.api.token_days); see IssueApiToken.
    'expiration' => null,

    // Makes leaked tokens recognisable to secret scanners.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'sd_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
