<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Laravel Page Speed
    |--------------------------------------------------------------------------
    |
    | Set this field to false to disable the laravel page speed service.
    | You would probably replace that in your local configuration to get a readable output.
    |
    */
    'enable' => env('LARAVEL_PAGE_SPEED_ENABLE', true),

    /*
    |--------------------------------------------------------------------------
    | Skip Routes
    |--------------------------------------------------------------------------
    |
    | Skip Routes paths to exclude.
    | You can use * as wildcard.
    |
    | Development/Debug Tools (Issue #164):
    | If you've customized the routes for debug tools in your application
    | (e.g., Horizon at '/admin/horizon' or Telescope at '/debug/telescope'),
    | you MUST update these patterns to match your custom routes.
    |
    | Examples of custom routes:
    |   'admin/horizon/*'      // Horizon::routes(['domain' => null, 'path' => 'admin/horizon'])
    |   'debug/telescope/*'    // Telescope::$path = 'debug/telescope'
    |   'my-path/clockwork/*'  // Custom Clockwork path
    |
    */
    'skip' => [
        // Development/Debug Tools (Issue #164)
        '_debugbar/*',    // Laravel Debugbar (usually fixed path)
        'horizon/*',      // Laravel Horizon (default, change if customized)
        '_ignition/*',    // Laravel Ignition - Error Pages (usually fixed path)
        'clockwork/*',    // Clockwork (default, change if customized)
        'telescope/*',    // Laravel Telescope (default, change if customized)

        // Binary/Document Files
        '*.xml',
        '*.less',
        '*.pdf',
        '*.doc',
        '*.txt',
        '*.ico',
        '*.rss',
        '*.zip',
        '*.mp3',
        '*.rar',
        '*.exe',
        '*.wmv',
        '*.doc',
        '*.avi',
        '*.ppt',
        '*.mpg',
        '*.mpeg',
        '*.tif',
        '*.wav',
        '*.mov',
        '*.psd',
        '*.ai',
        '*.xls',
        '*.mp4',
        '*.m4a',
        '*.swf',
        '*.dat',
        '*.dmg',
        '*.iso',
        '*.flv',
        '*.m4v',
        '*.torrent'
    ],

    /*
    |--------------------------------------------------------------------------
    | API Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Settings for API-specific middlewares that enhance performance and
    | observability without modifying response data.
    |
    */
    'api' => [
        /*
        | Response Compression Settings
        */
        'min_compression_size' => env('API_MIN_COMPRESSION_SIZE', 1024), // 1KB minimum
        'show_compression_metrics' => env('API_SHOW_COMPRESSION_METRICS', false),
        'skip_error_compression' => env('API_SKIP_ERROR_COMPRESSION', false),

        /*
        | Performance Headers Settings
        */
        'track_queries' => env('API_TRACK_QUERIES', false),
        'query_threshold' => env('API_QUERY_THRESHOLD', 20), // Warn if more than 20 queries
        'slow_request_threshold' => env('API_SLOW_REQUEST_THRESHOLD', 1000), // 1 second

        /*
        | ETag Settings
        */
        'etag_algorithm' => env('API_ETAG_ALGORITHM', 'md5'), // md5, sha1, or sha256
        'etag_max_age' => env('API_ETAG_MAX_AGE', 300), // 5 minutes

        /*
        | Security Headers Settings
        */
        'referrer_policy' => env('API_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'hsts_max_age' => env('API_HSTS_MAX_AGE', 31536000), // 1 year
        'hsts_include_subdomains' => env('API_HSTS_INCLUDE_SUBDOMAINS', false),
        'content_security_policy' => env('API_CSP', "default-src 'none'; frame-ancestors 'none'"),
        'permissions_policy' => env('API_PERMISSIONS_POLICY', 'geolocation=(), microphone=(), camera=()'),

        /*
        | Response Cache Settings (Advanced)
        */
        'cache' => [
            'enabled' => env('API_CACHE_ENABLED', false),
            'driver' => env('API_CACHE_DRIVER', 'redis'), // redis, memcached, file, array
            'ttl' => env('API_CACHE_TTL', 300), // seconds (5 minutes default)
            'per_user' => env('API_CACHE_PER_USER', false), // Separate cache per authenticated user
            'cache_authenticated' => env('API_CACHE_AUTHENTICATED', false), // Cache authenticated requests
            'track_metrics' => env('API_CACHE_TRACK_METRICS', true), // Track hit/miss metrics
            'vary_headers' => [], // Headers that affect caching (e.g., ['Accept-Language'])
            'cacheable_content_types' => [
                'application/json',
                'application/xml',
                'application/vnd.api+json',
            ],
        ],

        /*
        | Health Check Settings
        */
        'health' => [
            'endpoint' => env('API_HEALTH_ENDPOINT', '/health'), // Health check endpoint path
            'cache_results' => env('API_HEALTH_CACHE_RESULTS', true), // Cache health check results for 10s
            'include_app_info' => env('API_HEALTH_INCLUDE_APP_INFO', true), // Include app name/version
            'checks' => [
                'database' => env('API_HEALTH_CHECK_DATABASE', true),
                'cache' => env('API_HEALTH_CHECK_CACHE', true),
                'disk' => env('API_HEALTH_CHECK_DISK', true),
                'memory' => env('API_HEALTH_CHECK_MEMORY', true),
                'queue' => env('API_HEALTH_CHECK_QUEUE', false), // Disabled by default
            ],
            'thresholds' => [
                'database_ms' => env('API_HEALTH_THRESHOLD_DB_MS', 100), // Max DB response time
                'cache_ms' => env('API_HEALTH_THRESHOLD_CACHE_MS', 50), // Max cache response time
                'disk_usage_percent' => env('API_HEALTH_THRESHOLD_DISK_PERCENT', 90), // Max disk usage
                'memory_usage_percent' => env('API_HEALTH_THRESHOLD_MEMORY_PERCENT', 90), // Max memory usage
            ],
        ],

        /*
        | Circuit Breaker Settings
        */
        'circuit_breaker' => [
            'enabled' => env('API_CIRCUIT_BREAKER_ENABLED', false),
            'failure_threshold' => env('API_CIRCUIT_BREAKER_THRESHOLD', 5), // Failures before opening
            'timeout' => env('API_CIRCUIT_BREAKER_TIMEOUT', 60), // Seconds before half-open
            'scope' => env('API_CIRCUIT_BREAKER_SCOPE', 'endpoint'), // endpoint, route, or path
            'slow_threshold_ms' => env('API_CIRCUIT_BREAKER_SLOW_MS', 5000), // 5s = slow request
            'error_codes' => [500, 502, 503, 504], // Status codes that trigger failure
            'fallback_status_code' => env('API_CIRCUIT_BREAKER_FALLBACK_CODE', 503),
            'fallback_response' => null, // Custom callback for fallback response
        ],
    ],
];
