<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\LaravelPageSpeed\Middleware\ApiCircuitBreaker;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

/**
 * Robust tests for ApiCircuitBreaker with chaos engineering scenarios
 * 
 * Tests cover:
 * - State transitions (closed → open → half-open → closed)
 * - Failure detection
 * - Automatic recovery
 * - Concurrent failures
 * - Timeout scenarios
 * - Fallback responses
 */
class ApiCircuitBreakerTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ApiCircuitBreaker();
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->getMiddleware();

        config(['laravel-page-speed.enable' => true]);
        config(['laravel-page-speed.api.circuit_breaker.enabled' => true]);
        config(['laravel-page-speed.api.circuit_breaker.failure_threshold' => 3]);
        config(['laravel-page-speed.api.circuit_breaker.timeout' => 2]); // 2 seconds for tests

        Cache::flush();
    }

    /**
     * Test: Circuit starts in CLOSED state
     */
    public function test_circuit_starts_closed(): void
    {
        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200, ['Content-Type' => 'application/json']);

        $result = $this->middleware->handle($request, function () use ($response) {
            return $response;
        });

        $this->assertEquals('closed', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * Test: Successful requests keep circuit closed
     */
    public function test_successful_requests_keep_circuit_closed(): void
    {
        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200, ['Content-Type' => 'application/json']);

        // Make 10 successful requests
        for ($i = 0; $i < 10; $i++) {
            $result = $this->middleware->handle($request, function () use ($response) {
                return $response;
            });

            $this->assertEquals('closed', $result->headers->get('X-Circuit-Breaker-State'));
        }
    }

    /**
     * Test: Circuit opens after failure threshold
     */
    public function test_circuit_opens_after_threshold(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Cause 3 failures (threshold)
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->middleware->handle($request, function () {
                    return new Response('Error', 500, ['Content-Type' => 'application/json']);
                });
            } catch (\Exception $e) {
                // Some failures might throw exceptions
            }
        }

        // Next request should get fallback response (circuit open)
        $result = $this->middleware->handle($request, function () {
            throw new \Exception('Circuit should be open!');
        });

        $this->assertEquals('open', $result->headers->get('X-Circuit-Breaker-State'));
        $this->assertEquals(503, $result->getStatusCode());
    }

    /**
     * Test: Open circuit returns fallback immediately
     */
    public function test_open_circuit_returns_fallback(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Force circuit open
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Request should fail fast
        $startTime = microtime(true);
        $result = $this->middleware->handle($request, function () {
            usleep(100000); // 100ms delay - should not be reached
            return new Response('OK', 200);
        });
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertLessThan(50, $duration, 'Circuit should fail fast (< 50ms)');
        $this->assertEquals(503, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('Service Temporarily Unavailable', $data['error']);
    }

    /**
     * Test: Circuit transitions to HALF_OPEN after timeout
     */
    public function test_circuit_transitions_to_half_open(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Wait for timeout
        sleep(3); // timeout is 2 seconds

        // Next request should be in HALF_OPEN state
        $result = $this->middleware->handle($request, function () {
            return new Response('OK', 200, ['Content-Type' => 'application/json']);
        });

        $this->assertEquals('half_open', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * Test: Successful request in HALF_OPEN closes circuit
     */
    public function test_half_open_success_closes_circuit(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Wait for half-open
        sleep(3);

        // Successful test request
        $this->middleware->handle($request, function () {
            return new Response('OK', 200, ['Content-Type' => 'application/json']);
        });

        // Next request should be CLOSED
        $result = $this->middleware->handle($request, function () {
            return new Response('OK', 200, ['Content-Type' => 'application/json']);
        });

        $this->assertEquals('closed', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * Test: Failed request in HALF_OPEN reopens circuit
     */
    public function test_half_open_failure_reopens_circuit(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Wait for half-open
        sleep(3);

        // Failed test request
        $this->middleware->handle($request, function () {
            return new Response('Error', 500, ['Content-Type' => 'application/json']);
        });

        // Circuit should be OPEN again
        $result = $this->middleware->handle($request, function () {
            throw new \Exception('Should not reach here!');
        });

        $this->assertEquals('open', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * Test: Different endpoints have separate circuits
     */
    public function test_different_endpoints_have_separate_circuits(): void
    {
        $request1 = Request::create('/api/endpoint1', 'GET');
        $request2 = Request::create('/api/endpoint2', 'GET');

        // Fail endpoint1
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request1, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Endpoint1 should be open
        $result1 = $this->middleware->handle($request1, function () {});
        $this->assertEquals('open', $result1->headers->get('X-Circuit-Breaker-State'));

        // Endpoint2 should still be closed
        $result2 = $this->middleware->handle($request2, function () {
            return new Response('OK', 200, ['Content-Type' => 'application/json']);
        });
        $this->assertEquals('closed', $result2->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * CHAOS TEST: Slow responses trigger circuit breaker
     */
    public function test_slow_responses_trigger_circuit_breaker(): void
    {
        config(['laravel-page-speed.api.circuit_breaker.slow_threshold_ms' => 100]);

        $request = Request::create('/api/slow', 'GET');

        // Make 3 slow requests
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                usleep(150000); // 150ms - above threshold
                return new Response('OK', 200, ['Content-Type' => 'application/json']);
            });
        }

        // Circuit should be open
        $result = $this->middleware->handle($request, function () {});
        $this->assertEquals('open', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * CHAOS TEST: Exceptions trigger circuit breaker
     */
    public function test_exceptions_trigger_circuit_breaker(): void
    {
        $request = Request::create('/api/exception', 'GET');

        // Cause 3 exceptions
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->middleware->handle($request, function () {
                    throw new \Exception('Simulated failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Circuit should be open
        $result = $this->middleware->handle($request, function () {});
        $this->assertEquals('open', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * CHAOS TEST: Mixed success/failure doesn't open circuit
     */
    public function test_mixed_success_failure_doesnt_open(): void
    {
        $request = Request::create('/api/mixed', 'GET');

        // Alternate success and failure
        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 === 0) {
                $this->middleware->handle($request, function () {
                    return new Response('OK', 200, ['Content-Type' => 'application/json']);
                });
            } else {
                $this->middleware->handle($request, function () {
                    return new Response('Error', 500, ['Content-Type' => 'application/json']);
                });
            }
        }

        // Circuit should still be closed (successes reset counter)
        $result = $this->middleware->handle($request, function () {
            return new Response('OK', 200, ['Content-Type' => 'application/json']);
        });

        $this->assertEquals('closed', $result->headers->get('X-Circuit-Breaker-State'));
    }

    /**
     * CHAOS TEST: Concurrent requests during state transition
     */
    public function test_concurrent_requests_during_transition(): void
    {
        $request = Request::create('/api/concurrent', 'GET');

        // Open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Multiple concurrent requests should all get fallback
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->middleware->handle($request, function () {
                throw new \Exception('Should not reach!');
            });
        }

        // All should be open
        foreach ($results as $result) {
            $this->assertEquals('open', $result->headers->get('X-Circuit-Breaker-State'));
            $this->assertEquals(503, $result->getStatusCode());
        }
    }

    /**
     * Test: Retry-After header is set
     */
    public function test_retry_after_header_set(): void
    {
        $request = Request::create('/api/test', 'GET');

        // Open circuit
        for ($i = 0; $i < 3; $i++) {
            $this->middleware->handle($request, function () {
                return new Response('Error', 500, ['Content-Type' => 'application/json']);
            });
        }

        // Check fallback response
        $result = $this->middleware->handle($request, function () {});

        $this->assertTrue($result->headers->has('Retry-After'));
        $retryAfter = (int) $result->headers->get('Retry-After');
        $this->assertGreaterThanOrEqual(0, $retryAfter);
        $this->assertLessThanOrEqual(2, $retryAfter); // Max timeout is 2 seconds
    }

    /**
     * Test: Circuit breaker disabled passes through
     */
    public function test_disabled_circuit_breaker_passes_through(): void
    {
        config(['laravel-page-speed.api.circuit_breaker.enabled' => false]);

        $request = Request::create('/api/test', 'GET');

        // Even with failures, should pass through
        $result = $this->middleware->handle($request, function () {
            return new Response('Error', 500, ['Content-Type' => 'application/json']);
        });

        $this->assertFalse($result->headers->has('X-Circuit-Breaker-State'));
    }
}
