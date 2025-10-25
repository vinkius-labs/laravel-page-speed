<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class ApiResponseCompressionTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiResponseCompression();
    }

    public function test_compresses_large_json_response(): void
    {
        // Create a large JSON response
        $data = array_fill(0, 100, [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'description' => str_repeat('Lorem ipsum dolor sit amet ', 10),
        ]);

        $json = json_encode($data);

        $request = Request::create('/api/users', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $compressedResponse = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have compression headers
        $this->assertTrue($compressedResponse->headers->has('Content-Encoding'));
        $this->assertEquals('gzip', $compressedResponse->headers->get('Content-Encoding'));

        // Compressed content should be smaller
        $originalSize = strlen($json);
        $compressedSize = strlen($compressedResponse->getContent());
        $this->assertLessThan($originalSize, $compressedSize);
    }

    public function test_does_not_compress_small_responses(): void
    {
        $json = json_encode(['status' => 'ok']);

        $request = Request::create('/api/status', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT be compressed (too small)
        $this->assertFalse($result->headers->has('Content-Encoding'));
        $this->assertEquals($json, $result->getContent());
    }

    public function test_does_not_compress_html_responses(): void
    {
        $html = str_repeat('<p>Test content</p>', 100);

        $request = Request::create('/page', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT be compressed (not an API response)
        $this->assertFalse($result->headers->has('Content-Encoding'));
    }

    public function test_does_not_compress_when_client_does_not_support(): void
    {
        $data = array_fill(0, 100, ['test' => 'data']);
        $json = json_encode($data);

        $request = Request::create('/api/data', 'GET');
        // No Accept-Encoding header

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should NOT be compressed
        $this->assertFalse($result->headers->has('Content-Encoding'));
    }

    public function test_adds_vary_header(): void
    {
        $data = array_fill(0, 100, ['test' => 'data']);
        $json = json_encode($data);

        $request = Request::create('/api/data', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should have Vary header for proper caching
        $this->assertTrue($result->headers->has('Vary'));
        $this->assertEquals('Accept-Encoding', $result->headers->get('Vary'));
    }

    public function test_prefers_brotli_over_gzip(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('Brotli extension not available');
        }

        $data = array_fill(0, 100, ['test' => 'data']);
        $json = json_encode($data);

        $request = Request::create('/api/data', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip, br');

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should use Brotli when available
        $this->assertEquals('br', $result->headers->get('Content-Encoding'));
    }
}
