<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlatformSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('reton.security.force_https', false) && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        /** @var Response $response */
        $response = $next($request);

        $frame = (string) config('reton.security.frame_options', 'DENY');
        if ($frame !== '') {
            $response->headers->set('X-Frame-Options', $frame);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', (string) config('reton.security.referrer_policy', 'strict-origin-when-cross-origin'));

        $permissions = (string) config('reton.security.permissions_policy', '');
        if ($permissions !== '') {
            $response->headers->set('Permissions-Policy', $permissions);
        }

        if ((bool) config('reton.security.hsts_enabled', true) && $request->secure()) {
            $maxAge = (int) config('reton.security.hsts_max_age', 31536000);
            $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        if ((bool) config('reton.security.csp_enabled', true)) {
            $csp = implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "img-src 'self' data: https: blob:",
                "font-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self' ws: wss: https:",
            ]);

            $header = (bool) config('reton.security.csp_report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $csp);
        }

        return $response;
    }
}
