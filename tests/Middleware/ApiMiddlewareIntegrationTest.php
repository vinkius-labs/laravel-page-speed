<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiCircuitBreaker;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiHealthCheck;

/**
 * Integration tests for multiple middlewares working together
 * Critical to ensure no conflicts or bugs when stacking middlewares
 */
class ApiMiddlewareIntegrationTest extends TestCase
{
    protected function getMiddleware()
    {
        // Not using a single middleware for this test suite
        $this->middleware = null;
    }

    public function setUp(): void
    {
        parent::setUp();
        config([
            'laravel-page-speed.api.cache.enabled' => true,
            'laravel-page-speed.api.cache.driver' => 'array', // Use array driver for tests
        ]);
    }


    public function test_handles_full_middleware_stack_correctly()
    {
        // Make data large enough to trigger compression
        $largeData = str_repeat('Integration Test Data ', 50);
        $data = ['id' => 1, 'name' => 'Integration Test', 'value' => 123.45, 'largeData' => $largeData];
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();
        $etag = new ApiETag();
        $compression = new ApiResponseCompression();
        $cache = new ApiResponseCache();

        $response = $security->handle($request, function () use ($performance, $etag, $compression, $cache, $data, $request) {
            return $performance->handle($request, function () use ($etag, $compression, $cache, $data, $request) {
                return $etag->handle($request, function () use ($compression, $cache, $data, $request) {
                    return $compression->handle($request, function () use ($cache, $data, $request) {
                        return $cache->handle($request, function () use ($data) {
                            return new JsonResponse($data);
                        });
                    });
                });
            });
        });

        // Verify security headers
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));

        // Verify performance headers
        $this->assertTrue($response->headers->has('X-Response-Time'));
        $this->assertTrue($response->headers->has('X-Memory-Usage'));

        // Verify ETag
        $this->assertTrue($response->headers->has('ETag'));

        // Verify data integrity (decompress if compressed)
        $content = $response->getContent();
        if ($response->headers->get('Content-Encoding') === 'gzip') {
            $content = gzdecode($content);
        }
        $decoded = json_decode($content, true);
        $this->assertEquals($data, $decoded);
    }


    public function test_handles_cache_and_compression_together()
    {
        // Make data large enough to trigger compression (> 1KB)
        $largeString = str_repeat('test data compression ', 100);
        $data = ['cached' => true, 'compressed' => true, 'data' => $largeString];
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $compression = new ApiResponseCompression();
        $cache = new ApiResponseCache();

        // First request (cache miss)
        $response1 = $compression->handle($request, function () use ($cache, $data, $request) {
            return $cache->handle($request, function () use ($data) {
                return new JsonResponse($data);
            });
        });

        // Second request (cache hit)
        $response2 = $compression->handle($request, function () use ($cache, $data, $request) {
            return $cache->handle($request, function () use ($data) {
                return new JsonResponse($data);
            });
        });

        // Verify data integrity (decompress if compressed)
        $content1 = $response1->getContent();
        $content2 = $response2->getContent();

        if ($response1->headers->get('Content-Encoding') === 'gzip') {
            $content1 = gzdecode($content1);
        }
        if ($response2->headers->get('Content-Encoding') === 'gzip') {
            $content2 = gzdecode($content2);
        }

        $this->assertEquals($data, json_decode($content1, true));
        $this->assertEquals($data, json_decode($content2, true));
    }


    public function test_handles_etag_and_cache_together()
    {
        $data = ['id' => 1, 'etag_cache_test' => true];
        $request = Request::create('/api/test', 'GET');

        $etag = new ApiETag();
        $cache = new ApiResponseCache();

        // First request
        $response1 = $etag->handle($request, function () use ($cache, $data, $request) {
            return $cache->handle($request, function () use ($data) {
                return new JsonResponse($data);
            });
        });

        $etagValue = $response1->headers->get('ETag');
        $this->assertNotEmpty($etagValue);

        // Second request with If-None-Match
        $request2 = Request::create('/api/test', 'GET');
        $request2->headers->set('If-None-Match', $etagValue);

        $response2 = $etag->handle($request2, function () use ($cache, $data, $request2) {
            return $cache->handle($request2, function () use ($data) {
                return new JsonResponse($data);
            });
        });

        $this->assertEquals(304, $response2->getStatusCode());
    }


    public function test_handles_circuit_breaker_with_other_middlewares()
    {
        config(['laravel-page-speed.api.circuit_breaker.enabled' => true]);

        $data = ['circuit_test' => true];
        $request = Request::create('/api/test', 'GET');

        $circuitBreaker = new ApiCircuitBreaker();
        $compression = new ApiResponseCompression();
        $security = new ApiSecurityHeaders();

        $response = $circuitBreaker->handle($request, function () use ($compression, $security, $data, $request) {
            return $compression->handle($request, function () use ($security, $data, $request) {
                return $security->handle($request, function () use ($data) {
                    return new JsonResponse($data);
                });
            });
        });

        // Should have circuit breaker state header
        $this->assertTrue($response->headers->has('X-Circuit-Breaker-State'));

        // Should still have other middleware headers
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));

        // Data integrity
        $this->assertEquals($data, json_decode($response->getContent(), true));
    }


    public function test_handles_health_check_with_other_middlewares()
    {
        config(['laravel-page-speed.api.health.endpoint' => '/health']);

        $request = Request::create('/health', 'GET');

        $healthCheck = new ApiHealthCheck();
        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();

        $response = $healthCheck->handle($request, function () use ($security, $performance, $request) {
            return $security->handle($request, function () use ($performance, $request) {
                return $performance->handle($request, function () {
                    return new JsonResponse(['status' => 'pass']); // Fallback, won't be called
                });
            });
        });

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('checks', $data);
    }


    public function test_handles_all_middlewares_with_error_response()
    {
        $errorData = ['error' => 'Not Found', 'code' => 404];
        $request = Request::create('/api/test', 'GET');

        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();
        $compression = new ApiResponseCompression();

        $response = $security->handle($request, function () use ($performance, $compression, $errorData, $request) {
            return $performance->handle($request, function () use ($compression, $errorData, $request) {
                return $compression->handle($request, function () use ($errorData) {
                    return new JsonResponse($errorData, 404);
                });
            });
        });

        $this->assertEquals(404, $response->getStatusCode());

        // Security headers should still be present
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));

        // Performance headers should still be present
        $this->assertTrue($response->headers->has('X-Response-Time'));

        // Data should be intact
        $this->assertEquals($errorData, json_decode($response->getContent(), true));
    }


    public function test_handles_middleware_order_variations()
    {
        $data = ['order_test' => true, 'value' => 42];
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        // Order 1: Security -> Performance -> Compression
        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();
        $compression = new ApiResponseCompression();

        $response1 = $security->handle($request, function () use ($performance, $compression, $data, $request) {
            return $performance->handle($request, function () use ($compression, $data, $request) {
                return $compression->handle($request, function () use ($data) {
                    return new JsonResponse($data);
                });
            });
        });

        // Order 2: Compression -> Performance -> Security
        $response2 = $compression->handle($request, function () use ($performance, $security, $data, $request) {
            return $performance->handle($request, function () use ($security, $data, $request) {
                return $security->handle($request, function () use ($data) {
                    return new JsonResponse($data);
                });
            });
        });

        // Both should work and preserve data
        $content1 = $response1->getContent();
        $content2 = $response2->getContent();

        // Decompress if needed
        if ($response1->headers->get('Content-Encoding') === 'gzip') {
            $content1 = gzdecode($content1);
        }
        if ($response2->headers->get('Content-Encoding') === 'gzip') {
            $content2 = gzdecode($content2);
        }

        $this->assertEquals($data, json_decode($content1, true));
        $this->assertEquals($data, json_decode($content2, true));

        // Both should have all headers
        $this->assertTrue($response1->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response2->headers->has('X-Content-Type-Options'));
    }


    public function test_handles_cache_invalidation_across_middlewares()
    {
        $cache = new ApiResponseCache();
        $etag = new ApiETag();

        $request = Request::create('/api/test', 'GET');

        // First request
        $response1 = $cache->handle($request, function () use ($etag, $request) {
            return $etag->handle($request, function () {
                return new JsonResponse(['version' => 1]);
            });
        });

        // Clear cache
        Cache::flush();

        // Second request (should get new data)
        $response2 = $cache->handle($request, function () use ($etag, $request) {
            return $etag->handle($request, function () {
                return new JsonResponse(['version' => 2]);
            });
        });

        $data1 = json_decode($response1->getContent(), true);
        $data2 = json_decode($response2->getContent(), true);

        $this->assertEquals(1, $data1['version']);
        $this->assertEquals(2, $data2['version']);
    }


    public function test_handles_large_responses_with_all_middlewares()
    {
        // Generate 5MB response
        $largeData = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeData[] = [
                'id' => $i,
                'name' => "Item {$i}",
                'description' => str_repeat("Description {$i} ", 50),
                'metadata' => [
                    'tags' => ['tag1', 'tag2', 'tag3'],
                    'attributes' => ['color' => 'red', 'size' => 'M']
                ]
            ];
        }

        $request = Request::create('/api/large', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $security = new ApiSecurityHeaders();
        $compression = new ApiResponseCompression();
        $performance = new ApiPerformanceHeaders();

        $response = $security->handle($request, function () use ($compression, $performance, $largeData, $request) {
            return $compression->handle($request, function () use ($performance, $largeData, $request) {
                return $performance->handle($request, function () use ($largeData) {
                    return new JsonResponse($largeData);
                });
            });
        });

        // Should be compressed
        $this->assertTrue($response->headers->has('Content-Encoding'));

        // Should have performance metrics
        $this->assertTrue($response->headers->has('X-Response-Time'));
        $this->assertTrue($response->headers->has('X-Memory-Usage'));

        // Verify data integrity (spot check)
        $content = gzdecode($response->getContent());
        $decoded = json_decode($content, true);

        $this->assertCount(10000, $decoded);
        $this->assertEquals(0, $decoded[0]['id']);
        $this->assertEquals(9999, $decoded[9999]['id']);
    }


    public function test_handles_concurrent_requests_with_multiple_middlewares()
    {
        $compression = new ApiResponseCompression();
        $cache = new ApiResponseCache();
        $security = new ApiSecurityHeaders();

        $request = Request::create('/api/concurrent', 'GET');
        $request->headers->set('Accept-Encoding', 'gzip');

        $responses = [];

        // Simulate 10 concurrent requests
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $compression->handle($request, function () use ($cache, $security, $request, $i) {
                return $cache->handle($request, function () use ($security, $request, $i) {
                    return $security->handle($request, function () use ($i) {
                        return new JsonResponse(['request_id' => $i, 'data' => 'concurrent']);
                    });
                });
            });
        }

        // All responses should be valid
        foreach ($responses as $index => $response) {
            $this->assertEquals(200, $response->getStatusCode());

            // Verify data integrity (most important for concurrent requests)
            $content = $response->getContent();
            if ($response->headers->get('Content-Encoding') === 'gzip') {
                $content = gzdecode($content);
            }
            $decoded = json_decode($content, true);
            $this->assertArrayHasKey('data', $decoded);
            $this->assertEquals('concurrent', $decoded['data']);
        }
    }


    public function test_preserves_custom_headers_through_middleware_stack()
    {
        $data = ['custom_headers' => true];
        $request = Request::create('/api/test', 'GET');

        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();

        $response = $security->handle($request, function () use ($performance, $data, $request) {
            return $performance->handle($request, function () use ($data) {
                $response = new JsonResponse($data);
                $response->headers->set('X-Custom-Header', 'CustomValue');
                $response->headers->set('X-API-Version', '1.0');
                return $response;
            });
        });

        // Custom headers should be preserved
        $this->assertEquals('CustomValue', $response->headers->get('X-Custom-Header'));
        $this->assertEquals('1.0', $response->headers->get('X-API-Version'));

        // Middleware headers should also be present
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Response-Time'));
    }


    public function test_handles_json_validation_errors_through_stack()
    {
        $validationErrors = [
            'message' => 'The given data was invalid.',
            'errors' => [
                'email' => ['The email field is required.'],
                'password' => ['The password field is required.']
            ]
        ];

        $request = Request::create('/api/validate', 'POST');

        $security = new ApiSecurityHeaders();
        $performance = new ApiPerformanceHeaders();
        $compression = new ApiResponseCompression();

        $response = $security->handle($request, function () use ($performance, $compression, $validationErrors, $request) {
            return $performance->handle($request, function () use ($compression, $validationErrors, $request) {
                return $compression->handle($request, function () use ($validationErrors) {
                    return new JsonResponse($validationErrors, 422);
                });
            });
        });

        $this->assertEquals(422, $response->getStatusCode());

        $decoded = json_decode($response->getContent(), true);
        $this->assertEquals($validationErrors['message'], $decoded['message']);
        $this->assertArrayHasKey('errors', $decoded);
    }
}
