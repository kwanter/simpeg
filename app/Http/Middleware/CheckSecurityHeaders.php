<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class CheckSecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security'
        ];

        foreach ($requiredHeaders as $header) {
            if (!$response->headers->has($header)) {
                Log::critical("Missing security header: $header");
            }
        }

        return $response;
    }
}
