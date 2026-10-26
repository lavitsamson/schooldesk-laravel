<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Laravel\Horizon\Horizon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (docs/SECURITY.md), most importantly a Content Security
 * Policy: scripts only from our origin or with this request's nonce (Vite, Ziggy and Horizon
 * add it to their tags), no plugins, no framing, forms and requests to our origin only.
 *
 * Exceptions, each as narrow as possible:
 * - styles allow 'unsafe-inline': Vue's style bindings and Chart.js set inline styles;
 * - the Horizon dashboard (platform admins only) compiles its Vue templates in the browser
 *   and needs 'unsafe-eval';
 * - while `npm run dev` runs, the Vite dev server's origin (scripts, hot reload socket);
 * - with APP_DEBUG, server error pages are sent without a CSP so the debug page works.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        Horizon::cspNonce($nonce);

        $response = $next($request);

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ]);

        // Browsers ignore both on plain HTTP (and log an error for the opener policy).
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }

        if (! (config('app.debug') && $response->getStatusCode() >= 500)) {
            $response->headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $style = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        if ($request->is(trim((string) config('horizon.path'), '/').'/*')) {
            $script[] = "'unsafe-eval'";
        }

        $devServer = $this->viteDevServer();
        if ($devServer !== null) {
            $script[] = $devServer;
            $style[] = $devServer;
            $connect[] = $devServer;
            $connect[] = preg_replace('/^http/', 'ws', $devServer);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * The Vite dev server's origin while `npm run dev` runs (it writes public/hot).
     */
    private function viteDevServer(): ?string
    {
        $hot = Vite::hotFile();

        if (! is_file($hot)) {
            return null;
        }

        $url = parse_url(trim((string) file_get_contents($hot)));

        return isset($url['scheme'], $url['host'])
            ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '')
            : null;
    }
}
