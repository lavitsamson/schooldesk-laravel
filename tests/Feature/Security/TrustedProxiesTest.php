<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Production runs behind Caddy and Nginx (docs/DEPLOYMENT.md): the client IP (rate limits)
// and HTTPS (Secure cookies, HSTS) come from X-Forwarded-* only when a trusted proxy sent them.

beforeEach(function (): void {
    Route::get('/_test/client', fn (Request $request): array => [
        'ip' => $request->ip(),
        'secure' => $request->secure(),
    ]);
});

$forwarded = fn (string $from): array => [
    'REMOTE_ADDR' => $from,
    'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
    'HTTP_X_FORWARDED_PROTO' => 'https',
];

it('takes the client address and scheme from a trusted proxy', function () use ($forwarded): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12']);

    $this->withServerVariables($forwarded('172.18.0.5'))->getJson('/_test/client')
        ->assertExactJson(['ip' => '203.0.113.7', 'secure' => true]);
});

it('ignores forwarded headers from anyone else', function () use ($forwarded): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8,172.16.0.0/12']);

    $this->withServerVariables($forwarded('198.51.100.1'))->getJson('/_test/client')
        ->assertExactJson(['ip' => '198.51.100.1', 'secure' => false]);
});

it('trusts no proxy unless TRUSTED_PROXIES is set', function () use ($forwarded): void {
    expect(config('trustedproxy.proxies'))->toBeNull();

    $this->withServerVariables($forwarded('172.18.0.5'))->getJson('/_test/client')
        ->assertExactJson(['ip' => '172.18.0.5', 'secure' => false]);
});
