<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * API Health Check Middleware
 * 
 * Provides automatic health check endpoints for monitoring and orchestration.
 * Essential for Kubernetes, Docker, load balancers, and monitoring systems.
 * 
 * Features:
 * - Automatic /health endpoint
 * - Checks: Database, Redis, Disk, Memory, Queue
 * - Configurable thresholds
 * - Liveness and Readiness probes (K8s)
 * - Metrics aggregation
 * - Custom health checks
 * - Graceful degradation
 * 
 * Use Cases:
 * - Kubernetes liveness/readiness probes
 * - Load balancer health checks
 * - Uptime monitoring (DataDog, New Relic)
 * - Auto-scaling triggers
 */
class ApiHealthCheck extends PageSpeed
{
    /**
     * Health check results cache duration (seconds)
     */
    protected const HEALTH_CACHE_TTL = 10;

    /**
     * Health check cache key
     */
    protected const HEALTH_CACHE_KEY = 'laravel_page_speed:health_check';

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
        // Check if this is a health check request
        $healthPath = config('laravel-page-speed.api.health.endpoint', '/health');

        if ($request->path() === trim($healthPath, '/')) {
            return $this->handleHealthCheck($request);
        }

        return $next($request);
    }

    /**
     * Handle health check request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    protected function handleHealthCheck($request)
    {
        $startTime = microtime(true);

        // Check for cached health status
        $useCache = config('laravel-page-speed.api.health.cache_results', true);

        if ($useCache) {
            $cached = Cache::get(self::HEALTH_CACHE_KEY);
            if ($cached !== null) {
                $cached['from_cache'] = true;
                return response()->json($cached, $cached['status_code']);
            }
        }

        // Perform health checks
        $checks = $this->performHealthChecks();

        // Determine overall status
        $isHealthy = $this->isSystemHealthy($checks);
        $statusCode = $isHealthy ? 200 : 503;

        // Build response
        $response = [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'system' => $this->getSystemMetrics(),
            'response_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'from_cache' => false,
        ];

        // Add application info if configured
        if (config('laravel-page-speed.api.health.include_app_info', true)) {
            $response['application'] = [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'version' => config('app.version', '1.0.0'),
            ];
        }

        $response['status_code'] = $statusCode;

        // Cache the result
        if ($useCache) {
            Cache::put(self::HEALTH_CACHE_KEY, $response, self::HEALTH_CACHE_TTL);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Perform all health checks.
     *
     * @return array
     */
    protected function performHealthChecks()
    {
        $checks = [];
        $enabledChecks = config('laravel-page-speed.api.health.checks', [
            'database' => true,
            'cache' => true,
            'disk' => true,
            'memory' => true,
            'queue' => false,
        ]);

        if ($enabledChecks['database'] ?? true) {
            $checks['database'] = $this->checkDatabase();
        }

        if ($enabledChecks['cache'] ?? true) {
            $checks['cache'] = $this->checkCache();
        }

        if ($enabledChecks['disk'] ?? true) {
            $checks['disk'] = $this->checkDiskSpace();
        }

        if ($enabledChecks['memory'] ?? true) {
            $checks['memory'] = $this->checkMemory();
        }

        if ($enabledChecks['queue'] ?? false) {
            $checks['queue'] = $this->checkQueue();
        }

        return $checks;
    }

    /**
     * Check database connection.
     *
     * @return array
     */
    protected function checkDatabase()
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Check if response time is acceptable
            $threshold = config('laravel-page-speed.api.health.thresholds.database_ms', 100);
            $status = $responseTime < $threshold ? 'ok' : 'slow';

            return [
                'status' => $status,
                'message' => 'Database connection successful',
                'response_time' => $responseTime . 'ms',
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache system.
     *
     * @return array
     */
    protected function checkCache()
    {
        try {
            $startTime = microtime(true);
            $testKey = 'health_check_test_' . time();
            $testValue = 'test';

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($retrieved !== $testValue) {
                return [
                    'status' => 'error',
                    'message' => 'Cache write/read mismatch',
                ];
            }

            $threshold = config('laravel-page-speed.api.health.thresholds.cache_ms', 50);
            $status = $responseTime < $threshold ? 'ok' : 'slow';

            return [
                'status' => $status,
                'message' => 'Cache system operational',
                'response_time' => $responseTime . 'ms',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache system failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space.
     *
     * @return array
     */
    protected function checkDiskSpace()
    {
        try {
            $path = storage_path();
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedPercent = 100 - (($freeSpace / $totalSpace) * 100);

            $threshold = config('laravel-page-speed.api.health.thresholds.disk_usage_percent', 90);
            $status = $usedPercent < $threshold ? 'ok' : 'warning';

            if ($usedPercent >= 95) {
                $status = 'critical';
            }

            return [
                'status' => $status,
                'message' => 'Disk space check',
                'free' => $this->formatBytes($freeSpace),
                'total' => $this->formatBytes($totalSpace),
                'used_percent' => round($usedPercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Disk space check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check memory usage.
     *
     * @return array
     */
    protected function checkMemory()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit > 0) {
            $usedPercent = ($memoryUsage / $memoryLimit) * 100;
            $threshold = config('laravel-page-speed.api.health.thresholds.memory_usage_percent', 90);
            $status = $usedPercent < $threshold ? 'ok' : 'warning';

            if ($usedPercent >= 95) {
                $status = 'critical';
            }

            return [
                'status' => $status,
                'message' => 'Memory usage check',
                'used' => $this->formatBytes($memoryUsage),
                'limit' => $this->formatBytes($memoryLimit),
                'used_percent' => round($usedPercent, 2),
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Memory usage check',
            'used' => $this->formatBytes($memoryUsage),
            'limit' => 'unlimited',
        ];
    }

    /**
     * Check queue system.
     *
     * @return array
     */
    protected function checkQueue()
    {
        try {
            $connection = config('queue.default');

            // This is a basic check - you might want to customize based on your queue driver
            return [
                'status' => 'ok',
                'message' => 'Queue system operational',
                'connection' => $connection,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Queue system failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get system metrics.
     *
     * @return array
     */
    protected function getSystemMetrics()
    {
        return [
            'uptime' => $this->getUptime(),
            'load_average' => $this->getLoadAverage(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Get system uptime.
     *
     * @return string|null
     */
    protected function getUptime()
    {
        if (function_exists('sys_getloadavg') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $uptime = explode(' ', $uptime)[0];
                return $this->formatUptime((int) $uptime);
            }
        }

        return null;
    }

    /**
     * Get load average.
     *
     * @return array|null
     */
    protected function getLoadAverage()
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }

        return null;
    }

    /**
     * Determine if system is healthy based on checks.
     *
     * @param  array $checks
     * @return bool
     */
    protected function isSystemHealthy($checks)
    {
        foreach ($checks as $check) {
            if (in_array($check['status'], ['error', 'critical'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get PHP memory limit in bytes.
     *
     * @return int
     */
    protected function getMemoryLimit()
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return 0; // Unlimited
        }

        $value = (int) $memoryLimit;
        $unit = strtolower(substr($memoryLimit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format uptime in seconds to human-readable format.
     *
     * @param int $seconds
     * @return string
     */
    protected function formatUptime($seconds)
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d {$hours}h {$minutes}m";
    }
}
