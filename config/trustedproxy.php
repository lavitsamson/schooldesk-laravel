<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's TrustProxies middleware. Only requests from these addresses may set
    | X-Forwarded-*, which decide the client IP (rate limits, audit log) and HTTPS (Secure
    | cookies, HSTS). Empty trusts no one. In production the app runs behind Caddy and Nginx
    | on the Docker network (docs/DEPLOYMENT.md).
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
