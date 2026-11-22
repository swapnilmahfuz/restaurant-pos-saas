<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrowserResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        $scripts = $request->routeIs('staff.pos.counter', 'staff.pos.bill') ? "'self'" : "'none'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src {$scripts}; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');

        return $response;
    }
}
