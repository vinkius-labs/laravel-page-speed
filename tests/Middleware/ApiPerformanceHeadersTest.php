<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class ApiPerformanceHeadersTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiPerformanceHeaders();
    }

    public function test_adds_response_time_header(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            usleep(10000); // Sleep 10ms
            return $response;
        });

        // Should have response time header
        $this->assertTrue($result->headers->has('X-Response-Time'));

        $responseTime = $result->headers->get('X-Response-Time');
        $this->assertStringContainsString('ms', $responseTime);
    }

    public function test_adds_memory_usage_header(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have memory usage header
        $this->assertTrue($result->headers->has('X-Memory-Usage'));

        $memoryUsage = $result->headers->get('X-Memory-Usage');
        // Should contain a unit (B, KB, MB, etc.)
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s+(B|KB|MB|GB)/', $memoryUsage);
    }

    public function test_adds_request_id_header(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have request ID header
        $this->assertTrue($result->headers->has('X-Request-ID'));

        $requestId = $result->headers->get('X-Request-ID');
        $this->assertNotEmpty($requestId);

        // Should contain date and random hash
        $this->assertMatchesRegularExpression('/\d{14}-[a-f0-9]{8}/', $requestId);
    }

    public function test_does_not_add_headers_to_html_responses(): void
    {
        $html = '<html><body>Test</body></html>';

        $request = Request::create('/page', 'GET');
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT have performance headers
        $this->assertFalse($result->headers->has('X-Response-Time'));
        $this->assertFalse($result->headers->has('X-Memory-Usage'));
        $this->assertFalse($result->headers->has('X-Request-ID'));
    }

    public function test_tracks_query_count_when_enabled(): void
    {
        config(['laravel-page-speed.api.track_queries' => true]);

        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have query count header
        $this->assertTrue($result->headers->has('X-Query-Count'));

        $queryCount = $result->headers->get('X-Query-Count');
        $this->assertIsNumeric($queryCount);
    }

    public function test_unique_request_ids(): void
    {
        $json = json_encode(['status' => 'ok']);
        $request = Request::create('/api/status', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result1 = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Create new middleware instance for second request
        $middleware2 = new ApiPerformanceHeaders();
        $result2 = $middleware2->handle($request, function () use ($response) {
            return clone $response;
        });

        $id1 = $result1->headers->get('X-Request-ID');
        $id2 = $result2->headers->get('X-Request-ID');

        // Request IDs should be different
        $this->assertNotEquals($id1, $id2);
    }
}
