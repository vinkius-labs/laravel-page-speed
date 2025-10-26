# API Optimization Playbook (REST / JSON)

Deep-dive into the middleware stack that hardens, accelerates, and instruments Laravel APIs while keeping response bodies untouched.

---

## Table of Contents

- [1. Design Goals](#1-design-goals)
- [2. Stack Overview](#2-stack-overview)
- [3. Installation and Wiring](#3-installation-and-wiring)
- [4. Middleware Deep Dive](#4-middleware-deep-dive)
- [5. Observability and Telemetry](#5-observability-and-telemetry)
- [6. Integration Patterns](#6-integration-patterns)
- [7. Testing Strategy](#7-testing-strategy)
- [8. Failure Scenarios and Mitigations](#8-failure-scenarios-and-mitigations)

---

## 1. Design Goals

The API pipeline is built around four principles:

1. **Transport-only optimizations** – payloads are compressed, cached, or wrapped in headers without mutating JSON structures.
2. **Deterministic behaviour** – middleware order produces repeatable responses in multi-node clusters and under retries.
3. **First-class observability** – latency, request identifiers, and cache status surface through HTTP headers compatible with APMs.
4. **Security by default** – hardened response headers enforce modern browser and client behaviour.

---

## 2. Stack Overview

| Middleware                    | Category        | Primary Capability                               | Default Status |
|------------------------------|-----------------|---------------------------------------------------|----------------|
| `ApiSecurityHeaders`         | Hardening       | Applies CSP, HSTS, referrer, and permissions headers | Enabled        |
| `ApiResponseCache`           | Caching         | Serves GET requests from cache and invalidates on mutations | Disabled       |
| `ApiETag`                    | Validation      | Issues strong/weak ETags and honours conditional requests | Enabled        |
| `ApiResponseCompression`     | Transport       | Negotiates Brotli/Gzip compression with thresholds | Enabled        |
| `ApiPerformanceHeaders`      | Telemetry       | Emits timing, memory, query, and correlation headers | Enabled        |
| `ApiCircuitBreaker`          | Resilience      | Applies rolling failure window and fallback status code | Optional       |
| `ApiHealthCheck`             | Diagnostics     | Exposes `/health` probe with subsystem metrics       | Optional       |

The recommended order inside the `api` group places hardening first, then caching/meta validators, followed by compression and finally telemetry.

---

## 3. Installation and Wiring

1. Require and publish assets:

   ```bash
   composer require vinkius-labs/laravel-page-speed
   php artisan vendor:publish --provider="VinkiusLabs\\LaravelPageSpeed\\ServiceProvider"
   ```

2. Wire the stack using the configuration style for your Laravel version:

   **Laravel 10.x – `app/Http/Kernel.php`**

   ```php
   protected $middlewareGroups = [
       'api' => [
           // Core Laravel middleware …
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiCircuitBreaker::class,
           \VinkiusLabs\LaravelPageSpeed\Middleware\ApiHealthCheck::class,
       ],
   ];
   ```

   **Laravel 11.x / 12.x – `bootstrap/app.php`**

   Extend the same `->withMiddleware` closure used for the web stack:

   ```php
   $middleware->appendToGroup('api', [
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiCircuitBreaker::class,
       \VinkiusLabs\LaravelPageSpeed\Middleware\ApiHealthCheck::class,
   ]);
   ```

3. Toggle features via `config/laravel-page-speed.php` or environment variables (see `docs/CONFIGURATION.md`). Example `.env` block:

   ```env
   API_CACHE_ENABLED=true
   API_CACHE_DRIVER=redis
   API_CACHE_TTL=300
   API_CACHE_DYNAMIC_TAGS=true
   API_MIN_COMPRESSION_SIZE=1024
   API_SHOW_COMPRESSION_METRICS=false
   API_TRACK_QUERIES=true
   API_QUERY_THRESHOLD=20
   API_SLOW_REQUEST_THRESHOLD=1000
   API_ETAG_ALGORITHM=md5
   API_ETAG_MAX_AGE=300
   ```

---

## 4. Middleware Deep Dive

### 4.1 ApiSecurityHeaders
- Adds `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Content-Security-Policy`, and `Permissions-Policy`.
- All directives are configurable; defaults align with OWASP ASVS Level 2.
- Combine with Laravel's rate limiting and auth middleware for holistic protection.

### 4.2 ApiResponseCache
- Transparent caching for idempotent verbs (`GET`, `HEAD`) with dynamic tagging based on request path and optional user context.
- Mutation verbs (`POST`, `PUT`, `PATCH`, `DELETE`) evict matching cache entries using tag indexes—see `docs/API-CACHE.md` for a full walkthrough.
- Supports per-user segregation via `per_user` and `cache_authenticated` flags.

### 4.3 ApiETag
- Generates hashes using the configured algorithm (`md5`, `sha1`, `sha256`).
- Handles both strong and weak validators; uses `If-None-Match` for conditional GETs and returns `304` without body when the payload is unchanged.
- Plays well with Response Cache: when cache is hit, ETag is emitted from stored metadata.

### 4.4 ApiResponseCompression
- Negotiates compression based on `Accept-Encoding`. Prefers Brotli (`br`), falls back to Gzip when unsupported.
- Respects `min_compression_size` to avoid inflating small payloads.
- `skip_error_compression` prevents compressing 4xx/5xx bodies when troubleshooting.
- Optional metrics header: `X-Compression-Ratio`, `X-Original-Size`, and `X-Compressed-Size`.

### 4.5 ApiPerformanceHeaders
- Wraps the request lifecycle in a high-resolution timer.
- Emits headers:
  - `X-Response-Time`: milliseconds with two decimal precision.
  - `X-Memory-Usage`: memory delta.
  - `X-Query-Count`: total database queries executed (requires `track_queries`).
  - `X-Request-ID`: generated UUID v4 if not already set upstream.
  - `X-Performance-Warning`: descriptive message when thresholds are exceeded.
- Ideal for structured logging and correlation within distributed traces.

### 4.6 ApiCircuitBreaker
- Maintains a sliding window of failures keyed by URL or route depending on `scope`.
- On threshold breach, short-circuits subsequent requests for `timeout` seconds and emits configurable fallback response.
- Emits `X-Circuit-Breaker-State` header (`closed`, `half-open`, `open`).

### 4.7 ApiHealthCheck
- Exposes a JSON document summarizing database, cache, disk, memory, and queue health.
- Optional response caching (`cache_results`) reduces probe load; default TTL is 10 seconds.
- Use with Kubernetes readiness/liveness probes or ALB/Gateway health checks.

---

## 5. Observability and Telemetry

- **Headers**: Performance, cache, and breaker headers are parseable by Datadog, New Relic, Elastic APM, and custom log shippers.
- **Metrics correlation**: Pair `X-Request-ID` with structured logs (`Log::withContext`) to trace entire request lifecycles.
- **APM tagging**: Example (Laravel + OpenTelemetry):

  ```php
  app('telemetry')->currentSpan()?->setAttribute('http.api.cache_status', $response->headers->get('X-Cache-Status'));
  app('telemetry')->currentSpan()?->setAttribute('http.api.request_id', $response->headers->get('X-Request-ID'));
  ```

- **Dashboards**: export CSV of headers via Istio Envoy filters or API Gateway access logs to visualize hit rates and latency distribution.

---

## 6. Integration Patterns

### 6.1 Sanctum / Session-based APIs

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
];
```

### 6.2 Rate-limited Public APIs

```php
Route::middleware(['throttle:120,1', 'bindings', 'page-speed.api'])->group(function () {
    Route::get('/v1/products', ProductController::class);
    Route::post('/v1/products', ProductStoreController::class);
});
```

### 6.3 Conditional Instrumentation

Enable expensive telemetry only outside production:

```php
if (! app()->environment('production')) {
    $router->pushMiddlewareToGroup('api', \VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class);
}
```

---

## 7. Testing Strategy

- **Unit tests**: Validate header presence/values via PHPUnit’s `assertHeader`. Target classes already have coverage under `tests/Middleware`.
- **Contract tests**: Capture JSON schemas before enabling middleware to ensure payloads remain unmodified.
- **Load testing**: Run k6 or Locust with/without middleware to measure CPU, memory, and cache hit behaviour.
- **Chaos drills**: Force cache backend failures or simulate downstream outages to validate circuit breaker fallbacks.

---

## 8. Failure Scenarios and Mitigations

| Scenario                                  | Detection signal                               | Recommended action                                 |
|-------------------------------------------|------------------------------------------------|---------------------------------------------------|
| ETag mismatch due to proxy modifications  | Client never receives 304 responses            | Ensure upstream proxies preserve `ETag` headers.   |
| Cache poisoning by per-user data          | Users receive responses with foreign state     | Enable `per_user` cache segmentation.             |
| Slow compression on large payloads        | Increased `X-Response-Time` but low CPU idle   | Raise `min_compression_size` or enable Brotli via native extension. |
| Circuit breaker stuck in open state       | Constant `X-Circuit-Breaker-State: open`       | Increase `timeout` or lower `failure_threshold`.   |
| Health probe flapping                     | Alternating 200/503 on `/health`               | Enable `cache_results` and review thresholds.      |

Maintaining automated tests and observability pipelines for these edge cases ensures the API stack remains predictable even under extreme load.

