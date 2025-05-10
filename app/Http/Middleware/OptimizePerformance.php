<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class OptimizePerformance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Add performance headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Add cache control headers for static assets
        if ($request->is('*.css') || $request->is('*.js') || $request->is('*.jpg') || $request->is('*.png')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000');
        }

        // Enable compression for text-based responses
        if (str_contains($response->headers->get('Content-Type'), 'text/') ||
            str_contains($response->headers->get('Content-Type'), 'application/json')) {
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }
} 