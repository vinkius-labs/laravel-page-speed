<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiHealthCheck;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

/**
 * Robust tests for ApiHealthCheck with chaos engineering scenarios
 */
class ApiHealthCheckTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiHealthCheck();
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->getMiddleware();

        config(['laravel-page-speed.enable' => true]);
        config(['laravel-page-speed.api.health.endpoint' => '/health']);
        config(['laravel-page-speed.api.health.cache_results' => false]); // Disable cache for tests
    }

    /**
     * Test: Basic health check returns 200 OK
     */
    public function test_health_check_returns_ok(): void
    {
        $request = Request::create('/health', 'GET');

        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Should not reach next middleware!');
        });

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('healthy', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('system', $data);
    }

    /**
     * Test: Health check includes all enabled checks
     */
    public function test_health_check_includes_all_checks(): void
    {
        config(['laravel-page-speed.api.health.checks' => [
            'database' => true,
            'cache' => true,
            'disk' => true,
            'memory' => true,
            'queue' => false,
        ]]);

        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
        $this->assertArrayHasKey('disk', $data['checks']);
        $this->assertArrayHasKey('memory', $data['checks']);
        $this->assertArrayNotHasKey('queue', $data['checks']); // Disabled
    }

    /**
     * Test: Database check shows OK status
     */
    public function test_database_check_shows_ok(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertContains($data['checks']['database']['status'], ['ok', 'slow']);
        $this->assertArrayHasKey('response_time', $data['checks']['database']);
    }

    /**
     * Test: Cache check shows OK status
     */
    public function test_cache_check_shows_ok(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertContains($data['checks']['cache']['status'], ['ok', 'slow']);
        $this->assertArrayHasKey('response_time', $data['checks']['cache']);
    }

    /**
     * Test: Disk check shows OK status
     */
    public function test_disk_check_shows_ok(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertContains($data['checks']['disk']['status'], ['ok', 'warning']);
        $this->assertArrayHasKey('free', $data['checks']['disk']);
        $this->assertArrayHasKey('total', $data['checks']['disk']);
        $this->assertArrayHasKey('used_percent', $data['checks']['disk']);
    }

    /**
     * Test: Memory check shows OK status
     */
    public function test_memory_check_shows_ok(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertContains($data['checks']['memory']['status'], ['ok', 'warning']);
        $this->assertArrayHasKey('used', $data['checks']['memory']);
    }

    /**
     * Test: System metrics are included
     */
    public function test_system_metrics_included(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('php_version', $data['system']);
        $this->assertArrayHasKey('laravel_version', $data['system']);
    }

    /**
     * Test: Application info is included when enabled
     */
    public function test_application_info_included(): void
    {
        config(['laravel-page-speed.api.health.include_app_info' => true]);

        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('application', $data);
        $this->assertArrayHasKey('name', $data['application']);
        $this->assertArrayHasKey('environment', $data['application']);
    }

    /**
     * Test: Non-health endpoints pass through
     */
    public function test_non_health_endpoints_pass_through(): void
    {
        $request = Request::create('/api/users', 'GET');

        $nextCalled = false;
        $this->middleware->handle($request, function () use (&$nextCalled) {
            $nextCalled = true;
            return new \Illuminate\Http\Response('OK');
        });

        $this->assertTrue($nextCalled);
    }

    /**
     * Test: Custom health endpoint path
     */
    public function test_custom_health_endpoint_path(): void
    {
        config(['laravel-page-speed.api.health.endpoint' => '/api/status']);

        $request = Request::create('/api/status', 'GET');
        $response = $this->middleware->handle($request, function () {
            throw new \Exception('Should not reach here!');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('healthy', $data['status']);
    }

    /**
     * Test: Response time is included
     */
    public function test_response_time_included(): void
    {
        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('response_time', $data);
        $this->assertStringContainsString('ms', $data['response_time']);
    }

    /**
     * CHAOS TEST: Health check during high load
     */
    public function test_health_check_under_high_load(): void
    {
        // Simulate multiple concurrent health checks
        $responses = [];
        for ($i = 0; $i < 50; $i++) {
            $request = Request::create('/health', 'GET');
            $responses[] = $this->middleware->handle($request, function () {});
        }

        // All should succeed
        foreach ($responses as $response) {
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    /**
     * CHAOS TEST: Health check with cache enabled
     */
    public function test_health_check_caching(): void
    {
        config(['laravel-page-speed.api.health.cache_results' => true]);

        Cache::flush();

        $request = Request::create('/health', 'GET');

        // First request - not cached
        $response1 = $this->middleware->handle($request, function () {});
        $data1 = json_decode($response1->getContent(), true);
        $this->assertFalse($data1['from_cache']);

        // Second request - should be cached
        $response2 = $this->middleware->handle($request, function () {});
        $data2 = json_decode($response2->getContent(), true);
        $this->assertTrue($data2['from_cache']);
    }

    /**
     * CHAOS TEST: Health check with disabled checks
     */
    public function test_health_check_with_all_checks_disabled(): void
    {
        config(['laravel-page-speed.api.health.checks' => [
            'database' => false,
            'cache' => false,
            'disk' => false,
            'memory' => false,
            'queue' => false,
        ]]);

        $request = Request::create('/health', 'GET');
        $response = $this->middleware->handle($request, function () {});

        $data = json_decode($response->getContent(), true);

        // Should still return valid response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('healthy', $data['status']);
        $this->assertEmpty($data['checks']);
    }

    /**
     * Regression: Disk metrics unavailable should not crash health check.
     */
    public function test_health_check_handles_missing_disk_metrics(): void
    {
        $middleware = new class extends ApiHealthCheck {
            protected function getDiskSpaceStats($path)
            {
                return null;
            }
        };

        $request = Request::create('/health', 'GET');
        $response = $middleware->handle($request, function () {});

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('disk', $data['checks']);
        $this->assertEquals('warning', $data['checks']['disk']['status']);
        $this->assertEquals('Disk space metrics unavailable', $data['checks']['disk']['message']);
    }

    /**
     * Regression: Load average unavailable should not trigger PHP errors.
     */
    public function test_health_check_handles_missing_load_average(): void
    {
        $middleware = new class extends ApiHealthCheck {
            protected function getLoadAverage()
            {
                return null;
            }
        };

        $request = Request::create('/health', 'GET');
        $response = $middleware->handle($request, function () {});

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('load_average', $data['system']);
        $this->assertNull($data['system']['load_average']);
    }
}
