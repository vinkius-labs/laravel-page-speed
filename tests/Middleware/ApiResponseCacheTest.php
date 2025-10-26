<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

/**
 * Robust tests for ApiResponseCache with chaos engineering scenarios
 */
class ApiResponseCacheTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiResponseCache();
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->getMiddleware();

        // Enable API cache for tests
        config(['laravel-page-speed.enable' => true]);
        config(['laravel-page-speed.api.cache.enabled' => true]);
        config(['laravel-page-speed.api.cache.driver' => 'array']); // Use array driver for testing

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test: Basic cache miss and hit
     */
    public function test_cache_miss_then_hit(): void
    {
        $json = json_encode(['id' => 1, 'name' => 'Test']);

        $request = Request::create('/api/users/1', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        // First request - cache miss
        $result1 = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('MISS', $result1->headers->get('X-Cache-Status'));
        $this->assertEquals($json, $result1->getContent());

        // Second request - cache hit
        $result2 = $this->middleware->handle($request, function () use ($response) {
            // This shouldn't be called on cache hit
            throw new \Exception('Cache should have been hit!');
        });

        $this->assertEquals('HIT', $result2->headers->get('X-Cache-Status'));
        $this->assertEquals($json, $result2->getContent());
        $this->assertTrue($result2->headers->has('Age'));
    }

    /**
     * Test: POST requests are not cached
     */
    public function test_post_requests_not_cached(): void
    {
        $json = json_encode(['created' => true]);

        $request = Request::create('/api/users', 'POST');
        $response = new Response($json, 201, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertFalse($result->headers->has('X-Cache-Status'));
    }

    /**
     * Test: PUT requests invalidate cached GET responses.
     */
    public function test_put_requests_invalidate_cache(): void
    {
        $this->assertCacheInvalidatedBy('PUT');
    }

    /**
     * Test: PATCH requests invalidate cached GET responses.
     */
    public function test_patch_requests_invalidate_cache(): void
    {
        $this->assertCacheInvalidatedBy('PATCH', 200);
    }

    /**
     * Test: DELETE requests invalidate cached GET responses.
     */
    public function test_delete_requests_invalidate_cache(): void
    {
        $this->assertCacheInvalidatedBy('DELETE');
    }

    /**
     * Test: Different query strings create different cache keys
     */
    public function test_different_query_strings_create_different_cache_keys(): void
    {
        $json1 = json_encode(['page' => 1]);
        $json2 = json_encode(['page' => 2]);

        $request1 = Request::create('/api/users?page=1', 'GET');
        $response1 = new Response($json1, 200, ['Content-Type' => 'application/json']);

        $request2 = Request::create('/api/users?page=2', 'GET');
        $response2 = new Response($json2, 200, ['Content-Type' => 'application/json']);

        // Cache first request
        $result1 = $this->middleware->handle($request1, function () use ($response1) {
            return $response1;
        });

        // Second request should be cache miss (different query string)
        $result2 = $this->middleware->handle($request2, function () use ($response2) {
            return $response2;
        });

        $this->assertEquals('MISS', $result1->headers->get('X-Cache-Status'));
        $this->assertEquals('MISS', $result2->headers->get('X-Cache-Status'));
        $this->assertNotEquals($result1->getContent(), $result2->getContent());
    }

    /**
     * Test: Error responses are not cached
     */
    public function test_error_responses_not_cached(): void
    {
        $json = json_encode(['error' => 'Not found']);

        $request = Request::create('/api/users/999', 'GET');
        $response = new Response($json, 404, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertFalse($result->headers->has('X-Cache-Status'));
    }

    /**
     * CHAOS TEST: Cache driver failure
     */
    public function test_graceful_degradation_on_cache_failure(): void
    {
        // Simulate cache driver failure
        config(['laravel-page-speed.api.cache.driver' => 'invalid_driver']);

        $json = json_encode(['id' => 1, 'name' => 'Test']);
        $request = Request::create('/api/users/1', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        // Should not throw exception, just skip caching
        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals($json, $result->getContent());
        // Cache status might not be set due to error, but response should work
    }

    /**
     * CHAOS TEST: Concurrent requests (race condition)
     */
    public function test_concurrent_requests_dont_cause_issues(): void
    {
        $json = json_encode(['id' => 1, 'concurrent' => true]);
        $request = Request::create('/api/concurrent', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $callCount = 0;

        // Simulate 10 concurrent requests
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->middleware->handle($request, function () use ($response, &$callCount) {
                $callCount++;
                usleep(1000); // Simulate some processing time
                return $response;
            });
        }

        // First request should miss, subsequent should hit
        $this->assertGreaterThan(0, $callCount);
        $this->assertLessThan(10, $callCount); // Not all should call the handler

        // All should return the same content
        foreach ($results as $result) {
            $this->assertEquals($json, $result->getContent());
        }
    }

    /**
     * CHAOS TEST: Cache with no-cache header
     */
    public function test_respects_no_cache_request_header(): void
    {
        $json = json_encode(['id' => 1]);

        $request = Request::create('/api/users/1', 'GET');
        $request->headers->set('Cache-Control', 'no-cache');

        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should not cache due to no-cache header
        $this->assertFalse($result->headers->has('X-Cache-Status'));
    }

    /**
     * CHAOS TEST: Very large response
     */
    public function test_handles_large_responses(): void
    {
        // Create a 1MB response
        $largeData = array_fill(0, 10000, [
            'id' => 1,
            'data' => str_repeat('x', 100),
        ]);
        $json = json_encode($largeData);

        $request = Request::create('/api/large', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        // Should handle large responses without issues
        $result1 = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('MISS', $result1->headers->get('X-Cache-Status'));

        // Second request should hit cache
        $result2 = $this->middleware->handle($request, function () {
            throw new \Exception('Should use cache!');
        });

        $this->assertEquals('HIT', $result2->headers->get('X-Cache-Status'));
    }

    /**
     * CHAOS TEST: Rapid cache invalidation
     */
    public function test_survives_rapid_cache_flush(): void
    {
        $json = json_encode(['id' => 1]);
        $request = Request::create('/api/users/1', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        for ($i = 0; $i < 100; $i++) {
            // Cache
            $this->middleware->handle($request, function () use ($response) {
                return $response;
            });

            // Flush every 10 iterations
            if ($i % 10 === 0) {
                Cache::flush();
            }
        }

        // Should complete without errors
        $this->assertTrue(true);
    }

    /**
     * CHAOS TEST: Memory pressure (simulate low memory)
     */
    public function test_handles_memory_pressure(): void
    {
        // Create many cached entries
        for ($i = 0; $i < 100; $i++) {
            $json = json_encode(['id' => $i, 'data' => str_repeat('x', 1000)]);
            $request = Request::create('/api/users/' . $i, 'GET');
            $response = new Response($json, 200, ['Content-Type' => 'application/json']);

            $this->middleware->handle($request, function () use ($response) {
                return $response;
            });
        }

        // Should not crash
        $this->assertTrue(true);
    }

    /**
     * Test: Non-JSON responses are not cached
     */
    public function test_non_json_responses_not_cached(): void
    {
        $html = '<html><body>Test</body></html>';

        $request = Request::create('/page', 'GET');
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertFalse($result->headers->has('X-Cache-Status'));
    }

    /**
     * Test: Cache respects TTL
     */
    public function test_cache_respects_ttl(): void
    {
        config(['laravel-page-speed.api.cache.ttl' => 1]); // 1 second TTL

        $json = json_encode(['id' => 1, 'ttl_test' => true]);
        $request = Request::create('/api/ttl', 'GET');
        $response = new Response($json, 200, ['Content-Type' => 'application/json']);

        // First request - cache
        $result1 = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('MISS', $result1->headers->get('X-Cache-Status'));

        // Wait for TTL to expire
        sleep(2);

        // Should be cache miss again
        $result2 = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('MISS', $result2->headers->get('X-Cache-Status'));
    }

    /**
     * Test: Collection mutations purge all query variants automatically.
     */
    public function test_post_requests_purge_collection_variants(): void
    {
        config(['laravel-page-speed.api.cache.purge_methods' => ['POST', 'PUT', 'PATCH', 'DELETE']]);

        $listRequest = Request::create('/api/users?page=1', 'GET');
        $listResponse = new Response(json_encode(['page' => 1]), 200, ['Content-Type' => 'application/json']);

        $altListRequest = Request::create('/api/users?page=2&sort=name', 'GET');
        $altListResponse = new Response(json_encode(['page' => 2]), 200, ['Content-Type' => 'application/json']);

        $reflection = new \ReflectionMethod(ApiResponseCache::class, 'generateCacheKey');
        $reflection->setAccessible(true);
        $keyPage1 = $reflection->invoke($this->middleware, $listRequest);
        $keyPage2 = $reflection->invoke($this->middleware, $altListRequest);

        // Seed caches
        $this->middleware->handle($listRequest, function () use ($listResponse) {
            return $listResponse;
        });
        $this->middleware->handle($altListRequest, function () use ($altListResponse) {
            return $altListResponse;
        });

        $this->assertTrue(Cache::store('array')->has($keyPage1));
        $this->assertTrue(Cache::store('array')->has($keyPage2));

        // Confirm hits
        $cachedPage1 = $this->middleware->handle($listRequest, function () {
            throw new \Exception('Should hit cache for page 1');
        });
        $cachedPage2 = $this->middleware->handle($altListRequest, function () {
            throw new \Exception('Should hit cache for page 2');
        });

        $this->assertEquals('HIT', $cachedPage1->headers->get('X-Cache-Status'));
        $this->assertEquals('HIT', $cachedPage2->headers->get('X-Cache-Status'));

        // Mutation invalidates both variants
        $postRequest = Request::create('/api/users', 'POST');
        $this->middleware->handle($postRequest, function () {
            return new Response(json_encode(['created' => true]), 201, ['Content-Type' => 'application/json']);
        });

        $this->assertFalse(Cache::store('array')->has($keyPage1));
        $this->assertFalse(Cache::store('array')->has($keyPage2));

        // Fresh requests should miss and reflect new payload
        $updatedListResponse = new Response(json_encode(['page' => 1, 'fresh' => true]), 200, ['Content-Type' => 'application/json']);
        $freshResult = $this->middleware->handle($listRequest, function () use ($updatedListResponse) {
            return $updatedListResponse;
        });

        $this->assertEquals('MISS', $freshResult->headers->get('X-Cache-Status'));
        $this->assertStringContainsString('fresh', $freshResult->getContent());
    }

    /**
     * Test: Nested resource mutations purge parent list caches.
     */
    public function test_nested_resource_mutations_invalidate_parent_lists(): void
    {
        config(['laravel-page-speed.api.cache.purge_methods' => ['POST', 'PUT', 'PATCH', 'DELETE']]);

        $listRequest = Request::create('/api/users/1/posts?page=1', 'GET');
        $listResponse = new Response(json_encode(['posts' => [1, 2, 3]]), 200, ['Content-Type' => 'application/json']);

        $reflection = new \ReflectionMethod(ApiResponseCache::class, 'generateCacheKey');
        $reflection->setAccessible(true);
        $listKey = $reflection->invoke($this->middleware, $listRequest);

        $this->middleware->handle($listRequest, function () use ($listResponse) {
            return $listResponse;
        });

        $this->assertTrue(Cache::store('array')->has($listKey));

        // Confirm cached response
        $cachedList = $this->middleware->handle($listRequest, function () {
            throw new \Exception('Nested list should be cached');
        });
        $this->assertEquals('HIT', $cachedList->headers->get('X-Cache-Status'));

        // Delete nested resource => should purge list cache
        $deleteRequest = Request::create('/api/users/1/posts/42', 'DELETE');
        $this->middleware->handle($deleteRequest, function () {
            return new Response('', 204);
        });

        $this->assertFalse(Cache::store('array')->has($listKey));

        $updatedListResponse = new Response(json_encode(['posts' => [1, 3]]), 200, ['Content-Type' => 'application/json']);
        $freshList = $this->middleware->handle($listRequest, function () use ($updatedListResponse) {
            return $updatedListResponse;
        });

        $this->assertEquals('MISS', $freshList->headers->get('X-Cache-Status'));
        $this->assertStringContainsString('3', $freshList->getContent());
    }

    /**
     * Helper to ensure mutation verbs purge cached GET content.
     */
    protected function assertCacheInvalidatedBy(string $method, int $mutationStatus = 204): void
    {
        config(['laravel-page-speed.api.cache.purge_methods' => ['POST', 'PUT', 'PATCH', 'DELETE']]);

        $initialJson = json_encode(['id' => 1, 'state' => 'initial']);
        $updatedJson = json_encode(['id' => 1, 'state' => strtolower($method)]);

        $getRequest = Request::create('/api/users/1', 'GET');
        $mutationRequest = Request::create('/api/users/1', $method);

        $initialResponse = new Response($initialJson, 200, ['Content-Type' => 'application/json']);
        $updatedResponse = new Response($updatedJson, 200, ['Content-Type' => 'application/json']);

        $reflection = new \ReflectionMethod(ApiResponseCache::class, 'generateCacheKey');
        $reflection->setAccessible(true);
        $cacheKey = $reflection->invoke($this->middleware, $getRequest);
        $this->assertSame($cacheKey, $reflection->invoke($this->middleware, $mutationRequest));

        $this->middleware->handle($getRequest, function () use ($initialResponse) {
            return $initialResponse;
        });

        $this->assertTrue(Cache::store('array')->has($cacheKey));

        $cachedResponse = $this->middleware->handle($getRequest, function () {
            throw new \Exception('Should be served from cache');
        });

        $this->assertEquals('HIT', $cachedResponse->headers->get('X-Cache-Status'));

        $this->middleware->handle($mutationRequest, function () use ($mutationStatus) {
            return new Response('', $mutationStatus);
        });

        $this->assertFalse(Cache::store('array')->has($cacheKey));

        $freshResponse = $this->middleware->handle($getRequest, function () use ($updatedResponse) {
            return $updatedResponse;
        });

        $this->assertEquals('MISS', $freshResponse->headers->get('X-Cache-Status'));
        $this->assertEquals($updatedJson, $freshResponse->getContent());
    }
}
