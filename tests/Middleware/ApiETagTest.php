<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class ApiETagTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiETag();
    }

    public function test_adds_etag_header_to_json_response(): void
    {
        $json = json_encode(['id' => 1, 'name' => 'Test']);

        $request = Request::create('/api/users/1', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have ETag header
        $this->assertTrue($result->headers->has('ETag'));

        $etag = $result->headers->get('ETag');
        // ETag should be wrapped in quotes
        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }

    public function test_returns_304_when_etag_matches(): void
    {
        $json = json_encode(['id' => 1, 'name' => 'Test']);

        // First request to get the ETag
        $request1 = Request::create('/api/users/1', 'GET');
        $response1 = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result1 = $this->middleware->handle($request1, function () use ($response1) {
            return $response1;
        });

        $etag = $result1->headers->get('ETag');

        // Second request with If-None-Match header
        $request2 = Request::create('/api/users/1', 'GET');
        $request2->headers->set('If-None-Match', $etag);
        $response2 = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result2 = $this->middleware->handle($request2, function () use ($response2) {
            return $response2;
        });

        // Should return 304 Not Modified
        $this->assertEquals(304, $result2->getStatusCode());
        $this->assertEmpty($result2->getContent());
    }

    public function test_does_not_add_etag_to_post_requests(): void
    {
        $json = json_encode(['id' => 1, 'name' => 'Test']);

        $request = Request::create('/api/users', 'POST');
        $response = new Response($json, 201, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have ETag header (only GET requests)
        $this->assertFalse($result->headers->has('ETag'));
    }

    public function test_does_not_add_etag_to_error_responses(): void
    {
        $json = json_encode(['error' => 'Not found']);

        $request = Request::create('/api/users/999', 'GET');
        $response = new Response($json, 404, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have ETag header (error response)
        $this->assertFalse($result->headers->has('ETag'));
    }

    public function test_does_not_add_etag_to_html_responses(): void
    {
        $html = '<html><body>Test</body></html>';

        $request = Request::create('/page', 'GET');
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have ETag header (not an API response)
        $this->assertFalse($result->headers->has('ETag'));
    }

    public function test_different_content_generates_different_etag(): void
    {
        $json1 = json_encode(['id' => 1, 'name' => 'User 1']);
        $json2 = json_encode(['id' => 2, 'name' => 'User 2']);

        $request1 = Request::create('/api/users/1', 'GET');
        $response1 = new Response($json1, 200, ['Content-Type' => 'application/json']);

        $result1 = $this->middleware->handle($request1, function () use ($response1) {
            return $response1;
        });

        $request2 = Request::create('/api/users/2', 'GET');
        $response2 = new Response($json2, 200, ['Content-Type' => 'application/json']);

        $result2 = $this->middleware->handle($request2, function () use ($response2) {
            return $response2;
        });

        $etag1 = $result1->headers->get('ETag');
        $etag2 = $result2->headers->get('ETag');

        // ETags should be different for different content
        $this->assertNotEquals($etag1, $etag2);
    }

    public function test_adds_cache_control_header(): void
    {
        $json = json_encode(['id' => 1, 'name' => 'Test']);

        $request = Request::create('/api/users/1', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have Cache-Control header
        $this->assertTrue($result->headers->has('Cache-Control'));

        $cacheControl = $result->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=', $cacheControl);
    }
}
