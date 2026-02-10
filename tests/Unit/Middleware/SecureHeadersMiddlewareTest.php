<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SecureHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\SimpegTestCase;

class SecureHeadersMiddlewareTest extends SimpegTestCase
{
    public function test_sets_x_frame_options_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function test_sets_x_xss_protection_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
    }

    public function test_sets_x_content_type_options_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_sets_referrer_policy_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_sets_permissions_policy_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $this->assertEquals('geolocation=(), microphone=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_sets_strict_transport_security_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
        $this->assertEquals('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_sets_content_security_policy_header(): void
    {
        $request = Request::create();
        $response = new Response;

        $middleware = new SecureHeadersMiddleware;
        $result = $middleware->handle($request, $response);

        $this->assertTrue($result === $response);
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $expectedPolicy = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'";
        $this->assertEquals($expectedPolicy, $response->headers->get('Content-Security-Policy'));
    }
}
