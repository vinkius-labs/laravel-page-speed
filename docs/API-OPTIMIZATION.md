# 🚀 Laravel Page Speed - API Optimization Features

Laravel Page Speed now includes **powerful API optimization middlewares** that enhance performance, security, and observability **without modifying your response data**.

## ✨ Why These Features Are Incredible for APIs

All these middlewares follow a **golden rule**: **They NEVER modify your API response data**. They only:
- ✅ Compress data for transport (transparent to clients)
- ✅ Add performance/security headers
- ✅ Optimize caching and bandwidth
- ✅ Provide observability metrics

Your API contracts remain **100% intact** and **backward compatible**.

---

## 📦 Available API Middlewares

### 1. **ApiResponseCompression** 🗜️

Automatically compresses API responses using Brotli or Gzip.

**Benefits:**
- Reduces bandwidth usage by 60-80%
- Faster response times
- Lower hosting costs
- Transparent to clients (browsers auto-decompress)

**Example:**
```php
// app/Http/Kernel.php
protected $middleware = [
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
];
```

**Configuration:**
```php
// config/laravel-page-speed.php
'api' => [
    'min_compression_size' => 1024, // Only compress responses > 1KB
    'show_compression_metrics' => true, // Add X-Compression-Savings header
    'skip_error_compression' => false, // Compress error responses too
],
```

**Response Headers Added:**
```
Content-Encoding: br (or gzip)
Vary: Accept-Encoding
X-Original-Size: 15234 (if metrics enabled)
X-Compressed-Size: 3421 (if metrics enabled)
X-Compression-Savings: 77.54% (if metrics enabled)
```

**Real-World Impact:**
- 15KB JSON response → 3KB compressed (77% smaller)
- 100KB response → 15KB compressed (85% smaller)

---

### 2. **ApiPerformanceHeaders** 📊

Adds performance metrics to response headers for monitoring and optimization.

**Benefits:**
- Identify slow endpoints instantly
- Track database query performance
- Monitor memory usage
- Trace requests across logs

**Example:**
```php
protected $middleware = [
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
];
```

**Configuration:**
```php
'api' => [
    'track_queries' => true, // Track DB queries
    'query_threshold' => 20, // Warn if > 20 queries
    'slow_request_threshold' => 1000, // Warn if > 1 second
],
```

**Response Headers Added:**
```
X-Response-Time: 234.56ms
X-Memory-Usage: 2.34 MB
X-Query-Count: 8
X-Request-ID: 20251024142530-a3f9c2d1
X-Performance-Warning: High query count detected (if threshold exceeded)
```

**Use Cases:**
- **APM Integration**: Send metrics to DataDog, New Relic, etc.
- **Debugging**: Trace slow requests with Request-ID
- **Optimization**: Identify N+1 query problems

---

### 3. **ApiETag** ⚡

Implements smart caching with ETags and 304 Not Modified responses.

**Benefits:**
- Saves bandwidth (returns empty 304 instead of full response)
- Reduces server load
- Faster API responses for unchanged data
- Works with CDNs and proxies

**Example:**
```php
protected $middleware = [
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
];
```

**Configuration:**
```php
'api' => [
    'etag_algorithm' => 'md5', // md5, sha1, or sha256
    'etag_max_age' => 300, // Cache for 5 minutes
],
```

**How It Works:**

**First Request:**
```
GET /api/users/123
Response: 200 OK
ETag: "5d41402abc4b2a76b9719d911017c592"
Cache-Control: private, max-age=300, must-revalidate
Body: {"id": 123, "name": "John"}
```

**Second Request (data unchanged):**
```
GET /api/users/123
If-None-Match: "5d41402abc4b2a76b9719d911017c592"
Response: 304 Not Modified
Body: (empty - saves bandwidth!)
```

**Real-World Impact:**
- Unchanged data: **0 bytes transferred** (vs full response)
- Server CPU: **minimal** (just hash comparison)
- Client: Uses cached data automatically

---

### 4. **ApiSecurityHeaders** 🔒

Adds security headers to protect your API.

**Benefits:**
- Prevents common attacks (XSS, clickjacking, etc.)
- HTTPS enforcement with HSTS
- Compliance with security best practices
- Better security scores

**Example:**
```php
protected $middleware = [
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
];
```

**Configuration:**
```php
'api' => [
    'referrer_policy' => 'strict-origin-when-cross-origin',
    'hsts_max_age' => 31536000, // 1 year
    'hsts_include_subdomains' => false,
    'content_security_policy' => "default-src 'none'; frame-ancestors 'none'",
    'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
],
```

**Headers Added:**
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000 (HTTPS only)
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

**Protection Against:**
- ✅ MIME sniffing attacks
- ✅ Clickjacking
- ✅ XSS vulnerabilities
- ✅ Downgrade attacks (HTTP → HTTPS)

---

## 🎯 Recommended Setup

### For REST APIs:
```php
// app/Http/Kernel.php
protected $middleware = [
    // ... your existing middleware
    
    // API Optimization Stack
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
];
```

### For High-Traffic APIs:
```php
// Apply only to API routes
protected $middlewareGroups = [
    'api' => [
        // ... default API middleware
        \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
    ],
];
```

### For Monitoring/Debugging:
```php
// Only in development/staging
if (! app()->isProduction()) {
    $middleware[] = \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class;
}
```

---

## 📈 Real-World Performance Gains

### Before Laravel Page Speed API Middlewares:
```
GET /api/products
Response Size: 245 KB
Response Time: 450ms
Database Queries: 35 (N+1 problem!)
Cache: No
Security Headers: None
```

### After Laravel Page Speed API Middlewares:
```
GET /api/products
Response Size: 45 KB (82% smaller - compressed)
Response Time: 420ms
Database Queries: 35 (visible in headers - now you can fix!)
Cache: ETag enabled (304 on next request)
Security Headers: 7 headers added
Performance Metrics: Available in headers

Second Request (cached):
Response Size: 0 bytes (304 Not Modified)
Response Time: 15ms (just ETag check)
```

**Total Improvement:**
- 🚀 **Bandwidth**: 82% reduction
- ⚡ **Speed**: 96% faster on cache hit
- 🔒 **Security**: 7 protection headers
- 📊 **Visibility**: Full performance metrics

---

## 🔧 Environment Configuration

Add to your `.env` file:

```bash
# General
LARAVEL_PAGE_SPEED_ENABLE=true

# Compression
API_MIN_COMPRESSION_SIZE=1024
API_SHOW_COMPRESSION_METRICS=false
API_SKIP_ERROR_COMPRESSION=false

# Performance Tracking
API_TRACK_QUERIES=false
API_QUERY_THRESHOLD=20
API_SLOW_REQUEST_THRESHOLD=1000

# ETag Caching
API_ETAG_ALGORITHM=md5
API_ETAG_MAX_AGE=300

# Security
API_REFERRER_POLICY=strict-origin-when-cross-origin
API_HSTS_MAX_AGE=31536000
API_HSTS_INCLUDE_SUBDOMAINS=false
```

---

## 🧪 Testing

Run the test suite:

```bash
composer test

# Or specific tests
vendor/bin/phpunit tests/Middleware/ApiResponseCompressionTest.php
vendor/bin/phpunit tests/Middleware/ApiPerformanceHeadersTest.php
vendor/bin/phpunit tests/Middleware/ApiETagTest.php
vendor/bin/phpunit tests/Middleware/ApiSecurityHeadersTest.php
```

---

## 🎨 Integration Examples

### With Laravel Sanctum (SPA Authentication):
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    
    // Add API optimization
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
],
```

### With API Rate Limiting:
```php
Route::middleware([
    'throttle:60,1',
    'api.compression',
    'api.etag',
])->group(function () {
    // Your API routes
});
```

### With Conditional Middleware:
```php
// Only compress large responses
Route::get('/api/reports/large', function () {
    return response()->json($largeDataset);
})->middleware(['api.compression']);

// Only track performance in development
if (config('app.debug')) {
    Route::middleware(['api.performance'])->group(function () {
        // API routes
    });
}
```

---

## 🌟 Why This is Incredible for APIs

1. **Zero Breaking Changes**: Your API response data is **never modified**
2. **Plug & Play**: Just add middleware, no code changes needed
3. **Huge Performance Gains**: 60-85% bandwidth reduction + caching
4. **Production Ready**: Battle-tested patterns from major APIs
5. **Observable**: Built-in performance metrics and tracing
6. **Secure by Default**: Industry-standard security headers
7. **Framework Integration**: Works seamlessly with Laravel ecosystem

---

## 📚 Learn More

- [Main README](../README.md)
- [Configuration Guide](../config/laravel-page-speed.php)
- [Contributing](../CONTRIBUTING.md)
- [Changelog](../CHANGELOG.md)

---

