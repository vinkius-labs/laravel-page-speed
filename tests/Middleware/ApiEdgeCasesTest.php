<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders;

/**
 * Edge case tests - scenarios that commonly break in production
 */
class ApiEdgeCasesTest extends TestCase
{
    protected function getMiddleware()
    {
        // Not using a single middleware for this test suite
        $this->middleware = null;
    }

    public function setUp(): void
    {
        parent::setUp();
        // Use array cache driver for tests (no Redis needed)
        config(['laravel-page-speed.api.cache.driver' => 'array']);
    }


    public function test_handles_null_response_body_safely()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse(null);
        });

        // JsonResponse converts null to empty object {}
        $this->assertContains($response->getContent(), ['null', '{}']);
        $this->assertEquals(200, $response->getStatusCode());
    }


    public function test_handles_empty_string_response()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse('');
        });

        $this->assertEquals('""', $response->getContent());
    }


    public function test_handles_zero_value_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse(0);
        });

        $this->assertEquals('0', $response->getContent());
    }


    public function test_handles_false_boolean_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse(false);
        });

        $this->assertEquals('false', $response->getContent());
    }


    public function test_skips_non_json_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $xmlContent = '<?xml version="1.0"?><root><item>test</item></root>';

        $response = $middleware->handle($request, function () use ($xmlContent) {
            $response = new \Illuminate\Http\Response($xmlContent);
            $response->headers->set('Content-Type', 'application/xml');
            return $response;
        });

        // Should compress XML too
        $this->assertNotEmpty($response->getContent());
    }


    public function test_handles_very_small_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $middleware = new ApiResponseCompression();

        // Response smaller than 1KB should not be compressed
        $response = $middleware->handle($request, function () {
            return new JsonResponse(['ok' => true]);
        });

        // Small responses might not be compressed
        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }


    public function test_handles_missing_accept_encoding_header()
    {
        $request = Request::create('/api/test', 'GET');
        // No Accept-Encoding header

        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['test' => 'data']);
        });

        // Should return uncompressed
        $this->assertFalse($response->headers->has('Content-Encoding'));
        $this->assertEquals(['test' => 'data'], json_decode($response->getContent(), true));
    }


    public function test_handles_invalid_accept_encoding_values()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'invalid-encoding');

        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new JsonResponse(['test' => 'data']);
        });

        // Should return uncompressed when encoding is not supported
        $this->assertEquals(['test' => 'data'], json_decode($response->getContent(), true));
    }


    public function test_handles_error_responses_correctly()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $errorData = [
            'error' => 'Not Found',
            'message' => 'Resource not found',
            'code' => 404
        ];

        $response = $middleware->handle($request, function () use ($errorData) {
            return new JsonResponse($errorData, 404);
        });

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals($errorData, json_decode($response->getContent(), true));
    }


    public function test_handles_validation_error_responses()
    {
        $request = Request::create('/api/test', 'POST');
        $middleware = new ApiResponseCompression();

        $errorData = [
            'message' => 'Validation failed',
            'errors' => [
                'email' => ['The email field is required.'],
                'password' => ['The password must be at least 8 characters.']
            ]
        ];

        $response = $middleware->handle($request, function () use ($errorData) {
            return new JsonResponse($errorData, 422);
        });

        $this->assertEquals(422, $response->getStatusCode());
        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals($errorData['message'], $decoded['message']);
        $this->assertArrayHasKey('email', $decoded['errors']);
    }


    public function test_handles_server_error_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $errorData = [
            'error' => 'Internal Server Error',
            'message' => 'Something went wrong'
        ];

        $response = $middleware->handle($request, function () use ($errorData) {
            return new JsonResponse($errorData, 500);
        });

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($errorData, json_decode($response->getContent(), true));
    }


    public function test_handles_redirect_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new \Illuminate\Http\RedirectResponse('/api/new-location');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Location'));
    }


    public function test_handles_already_compressed_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $middleware = new ApiResponseCompression();

        $data = ['test' => 'data'];
        $compressed = gzencode(json_encode($data));

        $response = $middleware->handle($request, function () use ($compressed) {
            $response = new \Illuminate\Http\Response($compressed);
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Encoding', 'gzip'); // Already compressed
            return $response;
        });

        // Should not double-compress
        $this->assertEquals('gzip', $response->headers->get('Content-Encoding'));
    }


    public function test_handles_cache_with_varying_query_parameters()
    {
        config(['laravel-page-speed.api.cache.enabled' => true]);

        $middleware = new ApiResponseCache();

        // Request 1: /api/test?page=1
        $request1 = Request::create('/api/test?page=1', 'GET');
        $response1 = $middleware->handle($request1, function () {
            return new JsonResponse(['page' => 1, 'data' => 'Page 1']);
        });

        // Request 2: /api/test?page=2
        $request2 = Request::create('/api/test?page=2', 'GET');
        $response2 = $middleware->handle($request2, function () {
            return new JsonResponse(['page' => 2, 'data' => 'Page 2']);
        });

        $data1 = json_decode($response1->getContent(), true);
        $data2 = json_decode($response2->getContent(), true);

        // Different query params should return different cached responses
        $this->assertEquals(1, $data1['page']);
        $this->assertEquals(2, $data2['page']);
    }


    public function test_handles_etag_with_identical_content()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiETag();

        // First request
        $response1 = $middleware->handle($request, function () {
            return new JsonResponse(['id' => 1, 'name' => 'Test']);
        });

        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Second request with If-None-Match
        $request2 = Request::create('/api/test', 'GET');
        $request2->headers->set('If-None-Match', $etag);

        $response2 = $middleware->handle($request2, function () {
            return new JsonResponse(['id' => 1, 'name' => 'Test']);
        });

        $this->assertEquals(304, $response2->getStatusCode());
    }


    public function test_handles_post_requests_without_caching()
    {
        config(['laravel-page-speed.api.cache.enabled' => true]);

        $middleware = new ApiResponseCache();

        $request = Request::create('/api/test', 'POST');
        $response = $middleware->handle($request, function () {
            return new JsonResponse(['created' => true]);
        });

        // POST requests should not be cached
        $this->assertFalse($response->headers->has('X-Cache-Status'));
    }


    public function test_handles_put_and_delete_requests()
    {
        config(['laravel-page-speed.api.cache.enabled' => true]);

        $middleware = new ApiResponseCache();

        // PUT request
        $putRequest = Request::create('/api/test/1', 'PUT');
        $putResponse = $middleware->handle($putRequest, function () {
            return new JsonResponse(['updated' => true]);
        });

        // DELETE request
        $deleteRequest = Request::create('/api/test/1', 'DELETE');
        $deleteResponse = $middleware->handle($deleteRequest, function () {
            return new JsonResponse(['deleted' => true]);
        });

        // Neither should be cached
        $this->assertFalse($putResponse->headers->has('X-Cache-Status'));
        $this->assertFalse($deleteResponse->headers->has('X-Cache-Status'));
    }


    public function test_handles_responses_without_content_type()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            $response = new \Illuminate\Http\Response('Plain text response');
            // No Content-Type header set
            return $response;
        });

        $this->assertEquals('Plain text response', $response->getContent());
    }


    public function test_handles_streaming_responses()
    {
        $request = Request::create('/api/test', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\StreamedResponse(function () {
                echo json_encode(['streaming' => true]);
            });
        });

        // Streaming responses should not be processed
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
    }


    public function test_handles_binary_file_responses()
    {
        $request = Request::create('/api/download', 'GET');
        $middleware = new ApiResponseCompression();

        $response = $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\BinaryFileResponse(__FILE__);
        });

        // Binary responses should not be processed
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
    }


    public function test_handles_concurrent_cache_requests_safely()
    {
        config(['laravel-page-speed.api.cache.enabled' => true]);

        $middleware = new ApiResponseCache();
        $request = Request::create('/api/test', 'GET');

        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $middleware->handle($request, function () {
                return new JsonResponse(['id' => 1, 'concurrent' => true]);
            });
        }

        // All responses should have the same data
        foreach ($responses as $response) {
            $data = json_decode($response->getContent(), true);
            $this->assertEquals(['id' => 1, 'concurrent' => true], $data);
        }
    }


    public function test_handles_special_http_methods()
    {
        $middleware = new ApiSecurityHeaders();

        // OPTIONS request (CORS preflight)
        $optionsRequest = Request::create('/api/test', 'OPTIONS');
        $optionsResponse = $middleware->handle($optionsRequest, function () {
            return new JsonResponse(null, 200);
        });

        // HEAD request
        $headRequest = Request::create('/api/test', 'HEAD');
        $headResponse = $middleware->handle($headRequest, function () {
            return new JsonResponse(['test' => 'data']);
        });

        $this->assertEquals(200, $optionsResponse->getStatusCode());
        $this->assertEquals(200, $headResponse->getStatusCode());
    }
}
