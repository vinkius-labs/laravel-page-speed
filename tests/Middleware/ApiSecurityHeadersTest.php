<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class ApiSecurityHeadersTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiSecurityHeaders();
    }

    public function test_adds_security_headers_to_json_response(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have security headers
        $this->assertTrue($result->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $result->headers->get('X-Content-Type-Options'));

        $this->assertTrue($result->headers->has('X-Frame-Options'));
        $this->assertEquals('DENY', $result->headers->get('X-Frame-Options'));

        $this->assertTrue($result->headers->has('X-XSS-Protection'));
        $this->assertEquals('1; mode=block', $result->headers->get('X-XSS-Protection'));

        $this->assertTrue($result->headers->has('Referrer-Policy'));
        $this->assertTrue($result->headers->has('Content-Security-Policy'));
        $this->assertTrue($result->headers->has('Permissions-Policy'));
    }

    public function test_adds_hsts_header_for_https(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('https://api.example.com/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have HSTS header for HTTPS
        $this->assertTrue($result->headers->has('Strict-Transport-Security'));

        $hsts = $result->headers->get('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=', $hsts);
    }

    public function test_does_not_add_hsts_for_http(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('http://api.example.com/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have HSTS header for HTTP
        $this->assertFalse($result->headers->has('Strict-Transport-Security'));
    }

    public function test_does_not_add_headers_to_html_responses(): void
    {
        $html = '<html><body>Test</body></html>';

        $request = Request::create('/page', 'GET');
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have security headers for non-API responses
        $this->assertFalse($result->headers->has('X-Content-Type-Options'));
        $this->assertFalse($result->headers->has('Content-Security-Policy'));
    }

    public function test_does_not_override_existing_headers(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, [
            'Content-Type' => 'application/json',
            'X-Frame-Options' => 'SAMEORIGIN', // Custom value
        ]);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should keep existing header value
        $this->assertEquals('SAMEORIGIN', $result->headers->get('X-Frame-Options'));

        // Should still add other headers
        $this->assertTrue($result->headers->has('X-Content-Type-Options'));
    }

    public function test_content_security_policy_for_apis(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $csp = $result->headers->get('Content-Security-Policy');

        // API CSP should be restrictive
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }
}
