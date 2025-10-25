<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * API Performance Headers Middleware
 * 
 * Adds performance metrics to API response headers without modifying the payload.
 * Helps developers identify slow endpoints and optimize their APIs.
 * 
 * Headers added:
 * - X-Response-Time: Total response time in milliseconds
 * - X-Memory-Usage: Peak memory usage
 * - X-Query-Count: Number of database queries executed
 * - X-Request-ID: Unique request identifier for tracing
 */
class ApiPerformanceHeaders extends PageSpeed
{
    protected $startTime;
    protected $startMemory;
    protected $requestId;

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
        // Capture start metrics
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->requestId = $this->generateRequestId();

        // Enable query logging if needed
        $shouldLogQueries = config('laravel-page-speed.api.track_queries', false);
        if ($shouldLogQueries) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        // Only add headers to API responses
        if (! $this->shouldAddPerformanceHeaders($request, $response)) {
            return $response;
        }

        return $this->addPerformanceHeaders($response, $shouldLogQueries);
    }

    /**
     * Add performance headers to the response.
     *
     * @param  \Illuminate\Http\Response $response
     * @param  bool $shouldLogQueries
     * @return \Illuminate\Http\Response
     */
    protected function addPerformanceHeaders($response, $shouldLogQueries)
    {
        // Calculate response time in milliseconds
        $responseTime = round((microtime(true) - $this->startTime) * 1000, 2);

        // Calculate memory usage
        $memoryUsage = memory_get_peak_usage() - $this->startMemory;
        $memoryFormatted = $this->formatBytes($memoryUsage);

        // Add headers
        $response->headers->set('X-Response-Time', $responseTime . 'ms');
        $response->headers->set('X-Memory-Usage', $memoryFormatted);
        $response->headers->set('X-Request-ID', $this->requestId);

        // Add query count if enabled
        if ($shouldLogQueries) {
            $queryCount = count(DB::getQueryLog());
            $response->headers->set('X-Query-Count', (string) $queryCount);

            // Warn about potential N+1 queries
            if ($queryCount > config('laravel-page-speed.api.query_threshold', 20)) {
                $response->headers->set('X-Performance-Warning', 'High query count detected');
            }
        }

        // Add slow request warning
        $slowThreshold = config('laravel-page-speed.api.slow_request_threshold', 1000); // 1 second
        if ($responseTime > $slowThreshold) {
            $response->headers->set('X-Performance-Warning', 'Slow request detected');
        }

        return $response;
    }

    /**
     * Determine if performance headers should be added.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldAddPerformanceHeaders($request, $response)
    {
        // Check if middleware is enabled
        if (! $this->shouldProcessPageSpeed($request, $response)) {
            return false;
        }

        // Only add to API responses
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/xml')
            || str_contains($contentType, 'application/vnd.api+json');
    }

    /**
     * Generate a unique request ID for tracing.
     *
     * @return string
     */
    protected function generateRequestId()
    {
        return sprintf(
            '%s-%s',
            date('YmdHis'),
            substr(md5(uniqid((string) mt_rand(), true)), 0, 8)
        );
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
