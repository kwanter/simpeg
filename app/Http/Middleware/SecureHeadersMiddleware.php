namespace App\Http\Middleware;

use Closure;

class SecureHeadersMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy',
            "default-src 'self';
            script-src 'self' 'unsafe-inline' https://trusted.cdn.com;
            style-src 'self' 'unsafe-inline';
            img-src 'self' data:;
            font-src 'self' https://fonts.gstatic.com;
            connect-src 'self' https://api.example.com;
            frame-src 'none';
            media-src 'self'");

        return $response;
    }
}
