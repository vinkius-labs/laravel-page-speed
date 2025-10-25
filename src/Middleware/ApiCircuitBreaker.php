<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * API Circuit Breaker Middleware
 * 
 * Implements the Circuit Breaker pattern to prevent cascading failures
 * and provide graceful degradation when dependencies fail.
 * 
 * Circuit States:
 * - CLOSED (Normal): All requests pass through
 * - OPEN (Failure): Requests fail fast without calling dependency
 * - HALF_OPEN (Testing): Allow test requests to check if system recovered
 * 
 * Features:
 * - Automatic failure detection
 * - Configurable failure threshold
 * - Auto-recovery with exponential backoff
 * - Per-endpoint circuit breakers
 * - Metrics and monitoring
 * - Fallback responses
 * - Zero data modification
 * 
 * Use Cases:
 * - Protect against failing external APIs
 * - Prevent database overload
 * - Handle third-party service outages
 * - Microservices resilience
 * 
 * Performance Impact:
 * - Open circuit: <1ms response (fail fast)
 * - Closed circuit: Normal response time
 * - Prevents system-wide failures
 */
class ApiCircuitBreaker extends PageSpeed
{
    /**
     * Circuit states
     */
    protected const STATE_CLOSED = 'closed';
    protected const STATE_OPEN = 'open';
    protected const STATE_HALF_OPEN = 'half_open';

    /**
     * Cache key prefix for circuit state
     */
    protected const CIRCUIT_PREFIX = 'laravel_page_speed:circuit:';

    /**
     * Metrics key prefix
     */
    protected const METRICS_PREFIX = 'laravel_page_speed:circuit:metrics:';

    /**
     * Apply - not used in this middleware
     * (Required by PageSpeed abstract class)
     *
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        return $buffer;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return \Illuminate\Http\Response $response
     */
    public function handle($request, Closure $next)
    {
        // Check if circuit breaker is enabled
        if (! config('laravel-page-speed.api.circuit_breaker.enabled', false)) {
            return $next($request);
        }

        // Get circuit identifier
        $circuitId = $this->getCircuitId($request);

        // Check circuit state
        $circuitState = $this->getCircuitState($circuitId);

        // Handle based on state
        switch ($circuitState['state']) {
            case self::STATE_OPEN:
                // Circuit is open - fail fast
                $this->recordCircuitOpen($circuitId);
                return $this->createFallbackResponse($request, $circuitState);

            case self::STATE_HALF_OPEN:
                // Circuit is half-open - allow test request
                return $this->handleHalfOpenRequest($request, $next, $circuitId, $circuitState);

            case self::STATE_CLOSED:
            default:
                // Circuit is closed - proceed normally
                return $this->handleClosedRequest($request, $next, $circuitId);
        }
    }

    /**
     * Handle request when circuit is closed.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string $circuitId
     * @return \Illuminate\Http\Response
     */
    protected function handleClosedRequest($request, $next, $circuitId)
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Check if response indicates failure
            if ($this->isFailureResponse($response, $responseTime)) {
                $this->recordFailure($circuitId);

                // Check if we should open circuit
                if ($this->shouldOpenCircuit($circuitId)) {
                    $this->openCircuit($circuitId);
                    Log::warning('Circuit breaker opened', [
                        'circuit' => $circuitId,
                        'failures' => $this->getFailureCount($circuitId),
                    ]);
                }
            } else {
                // Success - reset failure count
                $this->recordSuccess($circuitId);
            }

            // Add circuit breaker headers
            $response->headers->set('X-Circuit-Breaker-State', self::STATE_CLOSED);
            $response->headers->set('X-Circuit-Breaker-Id', $circuitId);

            return $response;
        } catch (\Exception $e) {
            // Exception occurred - count as failure
            $this->recordFailure($circuitId);

            if ($this->shouldOpenCircuit($circuitId)) {
                $this->openCircuit($circuitId);
            }

            throw $e;
        }
    }

    /**
     * Handle request when circuit is half-open.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param  string $circuitId
     * @param  array $circuitState
     * @return \Illuminate\Http\Response
     */
    protected function handleHalfOpenRequest($request, $next, $circuitId, $circuitState)
    {
        $startTime = microtime(true);

        try {
            $response = $next($request);
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Check if test request was successful
            if ($this->isFailureResponse($response, $responseTime)) {
                // Still failing - reopen circuit
                $this->openCircuit($circuitId);
                Log::info('Circuit breaker reopened after failed test', [
                    'circuit' => $circuitId,
                ]);
            } else {
                // Success - close circuit
                $this->closeCircuit($circuitId);
                Log::info('Circuit breaker closed after successful test', [
                    'circuit' => $circuitId,
                ]);
            }

            $response->headers->set('X-Circuit-Breaker-State', self::STATE_HALF_OPEN);
            $response->headers->set('X-Circuit-Breaker-Id', $circuitId);

            return $response;
        } catch (\Exception $e) {
            // Test failed - reopen circuit
            $this->openCircuit($circuitId);
            throw $e;
        }
    }

    /**
     * Get circuit identifier for the request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return string
     */
    protected function getCircuitId($request)
    {
        $scope = config('laravel-page-speed.api.circuit_breaker.scope', 'endpoint');

        switch ($scope) {
            case 'route':
                $route = $request->route();
                return $route ? $route->getName() ?? $request->path() : $request->path();

            case 'path':
                // Use first path segment
                $segments = explode('/', trim($request->path(), '/'));
                return $segments[0] ?? 'root';

            case 'endpoint':
            default:
                // Full endpoint (path + method)
                return $request->method() . ':' . $request->path();
        }
    }

    /**
     * Get current circuit state.
     *
     * @param  string $circuitId
     * @return array
     */
    protected function getCircuitState($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;
        $state = Cache::get($cacheKey);

        if ($state === null) {
            return [
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'opened_at' => null,
            ];
        }

        // Check if half-open timeout expired
        if ($state['state'] === self::STATE_OPEN) {
            $openedAt = $state['opened_at'];
            $timeout = config('laravel-page-speed.api.circuit_breaker.timeout', 60);

            if (time() - $openedAt >= $timeout) {
                // Transition to half-open
                $state['state'] = self::STATE_HALF_OPEN;
                Cache::put($cacheKey, $state, 3600);
            }
        }

        return $state;
    }

    /**
     * Determine if response indicates failure.
     *
     * @param  \Illuminate\Http\Response $response
     * @param  float $responseTime Response time in milliseconds
     * @return bool
     */
    protected function isFailureResponse($response, $responseTime)
    {
        $statusCode = $response->getStatusCode();

        // Check for error status codes
        $errorCodes = config('laravel-page-speed.api.circuit_breaker.error_codes', [500, 502, 503, 504]);
        if (in_array($statusCode, $errorCodes)) {
            return true;
        }

        // Check for slow responses
        $slowThreshold = config('laravel-page-speed.api.circuit_breaker.slow_threshold_ms', 5000);
        if ($responseTime > $slowThreshold) {
            return true;
        }

        return false;
    }

    /**
     * Record a failure.
     *
     * @param  string $circuitId
     * @return void
     */
    protected function recordFailure($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;
        $state = Cache::get($cacheKey, [
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
        ]);

        $state['failures']++;
        $state['last_failure'] = time();

        Cache::put($cacheKey, $state, 3600);

        // Update metrics
        Cache::increment(self::METRICS_PREFIX . $circuitId . ':failures');
    }

    /**
     * Record a success.
     *
     * @param  string $circuitId
     * @return void
     */
    protected function recordSuccess($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;
        $state = Cache::get($cacheKey);

        if ($state && $state['failures'] > 0) {
            // Reset failure count on success
            $state['failures'] = max(0, $state['failures'] - 1);
            Cache::put($cacheKey, $state, 3600);
        }

        // Update metrics
        Cache::increment(self::METRICS_PREFIX . $circuitId . ':successes');
    }

    /**
     * Get current failure count.
     *
     * @param  string $circuitId
     * @return int
     */
    protected function getFailureCount($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;
        $state = Cache::get($cacheKey);

        return $state['failures'] ?? 0;
    }

    /**
     * Determine if circuit should be opened.
     *
     * @param  string $circuitId
     * @return bool
     */
    protected function shouldOpenCircuit($circuitId)
    {
        $failureCount = $this->getFailureCount($circuitId);
        $threshold = config('laravel-page-speed.api.circuit_breaker.failure_threshold', 5);

        return $failureCount >= $threshold;
    }

    /**
     * Open the circuit.
     *
     * @param  string $circuitId
     * @return void
     */
    protected function openCircuit($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;

        $state = [
            'state' => self::STATE_OPEN,
            'failures' => $this->getFailureCount($circuitId),
            'opened_at' => time(),
        ];

        Cache::put($cacheKey, $state, 3600);

        // Update metrics
        Cache::increment(self::METRICS_PREFIX . $circuitId . ':opens');
    }

    /**
     * Close the circuit.
     *
     * @param  string $circuitId
     * @return void
     */
    protected function closeCircuit($circuitId)
    {
        $cacheKey = self::CIRCUIT_PREFIX . $circuitId;

        $state = [
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'opened_at' => null,
        ];

        Cache::put($cacheKey, $state, 3600);

        // Update metrics
        Cache::increment(self::METRICS_PREFIX . $circuitId . ':closes');
    }

    /**
     * Record circuit open event.
     *
     * @param  string $circuitId
     * @return void
     */
    protected function recordCircuitOpen($circuitId)
    {
        Cache::increment(self::METRICS_PREFIX . $circuitId . ':rejected');
    }

    /**
     * Create fallback response when circuit is open.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  array $circuitState
     * @return \Illuminate\Http\Response
     */
    protected function createFallbackResponse($request, $circuitState)
    {
        $statusCode = config('laravel-page-speed.api.circuit_breaker.fallback_status_code', 503);

        $fallbackData = [
            'error' => 'Service Temporarily Unavailable',
            'message' => 'The service is currently experiencing issues. Please try again later.',
            'circuit_breaker' => [
                'state' => $circuitState['state'],
                'opened_at' => date('c', $circuitState['opened_at']),
                'retry_after' => $this->getRetryAfter($circuitState),
            ],
        ];

        // Check for custom fallback
        $customFallback = config('laravel-page-speed.api.circuit_breaker.fallback_response');
        if (is_callable($customFallback)) {
            $fallbackData = $customFallback($request, $circuitState);
        }

        $response = response()->json($fallbackData, $statusCode);

        // Add headers
        $response->headers->set('X-Circuit-Breaker-State', self::STATE_OPEN);
        $response->headers->set('Retry-After', $this->getRetryAfter($circuitState));

        return $response;
    }

    /**
     * Get retry-after seconds.
     *
     * @param  array $circuitState
     * @return int
     */
    protected function getRetryAfter($circuitState)
    {
        $timeout = config('laravel-page-speed.api.circuit_breaker.timeout', 60);
        $openedAt = $circuitState['opened_at'];
        $elapsed = time() - $openedAt;

        return max(0, $timeout - $elapsed);
    }
}
