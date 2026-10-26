<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

it('sends a nonce-based content security policy with every page', function (): void {
    $response = $this->get('/login')->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy');
    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->not->toContain('unsafe-eval')
        ->not->toContain('fonts.bunny.net');

    preg_match("/script-src 'self' 'nonce-([A-Za-z0-9]+)'/", $policy, $match);
    expect($match)->toHaveCount(2);

    // Every script tag on the page (Ziggy's routes, Vite's entry points) carries the nonce.
    $html = (string) $response->getContent();
    preg_match_all('/<script\b[^>]*>/', $html, $tags);
    expect($tags[0])->not->toBeEmpty();
    foreach ($tags[0] as $tag) {
        expect($tag)->toContain('nonce="'.$match[1].'"');
    }
});

it('uses a new nonce for every request', function (): void {
    $nonce = fn (): string => (string) preg_replace("/.*'nonce-([A-Za-z0-9]+)'.*/", '$1', (string) $this->get('/login')->headers->get('Content-Security-Policy'));

    expect($nonce())->not->toBe($nonce());
});

it('sends the other security headers, on API responses too', function (string $uri): void {
    $this->getJson($uri)->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Content-Security-Policy');
})->with(['/login', '/api/v1/me']);

it('asks browsers to keep to HTTPS only on HTTPS requests', function (): void {
    $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('allows eval only for the Horizon dashboard', function (): void {
    $user = User::factory()->create(['is_platform_admin' => true]);

    $policy = (string) $this->actingAs($user)->get('/horizon/dashboard')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("'unsafe-eval'");
});

it('does not serve the private disk through Laravel\'s storage route', function (): void {
    expect(Route::has('storage.local'))->toBeFalse();
});
