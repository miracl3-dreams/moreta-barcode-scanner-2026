<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // camera stays self-allowed: scanning is this app's whole purpose, and
        // the backend may serve the built SPA in production.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        // Only advertise HSTS over TLS. Sending it on plain HTTP is ignored by
        // browsers and would be wrong on the LAN deployment, which still
        // terminates without TLS.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
