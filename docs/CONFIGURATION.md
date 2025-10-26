# Configuration Reference

Authoritative guide for `config/laravel-page-speed.php` covering every parameter, recommended defaults, and operational guidance.

---

## Table of Contents

- [1. File Overview](#1-file-overview)
- [2. Global Settings](#2-global-settings)
- [3. Skip Rules](#3-skip-rules)
- [4. API Module](#4-api-module)
  - [4.1 Compression](#41-compression)
  - [4.2 Performance Headers](#42-performance-headers)
  - [4.3 ETag](#43-etag)
  - [4.4 Security Headers](#44-security-headers)
  - [4.5 Cache](#45-cache)
  - [4.6 Health Check](#46-health-check)
  - [4.7 Circuit Breaker](#47-circuit-breaker)
- [5. How to Use](#5-how-to-use)
- [6. When to Use](#6-when-to-use)

---

## 1. File Overview

The published configuration file groups options by concern. Environment variables can override any value to support per-environment tuning. All sections can be toggled independently.

```php
return [
    'enable' => env('LARAVEL_PAGE_SPEED_ENABLE', true),
    'skip' => [ /* route patterns */ ],
    'api' => [
        'compression' => [],
        'performance' => [],
        'etag' => [],
        'security' => [],
        'cache' => [],
        'health' => [],
        'circuit_breaker' => [],
    ],
];
```

---

## 2. Global Settings

| Key          | Type    | Default | Description                                             |
|--------------|---------|---------|---------------------------------------------------------|
| `enable`     | bool    | `true`  | Master switch. Disable to bypass all optimizations.    |
| `skip`       | array   | See file | List of URI patterns (supports `*` wildcard) to bypass. |

**Tip:** Use `LARAVEL_PAGE_SPEED_ENABLE=false` in development for easier HTML inspection.

---

## 3. Skip Rules

Default patterns cover common debug tooling:

```php
'skip' => [
    '_debugbar/*',
    'horizon/*',
    '_ignition/*',
    'clockwork/*',
    'telescope/*',
    '*.xml', '*.pdf', '*.zip', // binary documents
];
```

Add custom entries for admin panels, downloads, or third-party integrations sensitive to minification/compression. Wildcards apply to path segments but do not span `/` by default.

---

## 4. API Module

### 4.1 Compression

| Key                        | Type | Default | Description |
|----------------------------|------|---------|-------------|
| `min_compression_size`     | int  | `1024`  | Only compress responses above this size (bytes). |
| `show_compression_metrics` | bool | `false` | Emit `X-Compression-Ratio`, `X-Original-Size`, `X-Compressed-Size`. |
| `skip_error_compression`   | bool | `false` | Keep error payloads uncompressed for debugging clarity. |

### 4.2 Performance Headers

| Key                    | Type | Default | Description |
|------------------------|------|---------|-------------|
| `track_queries`        | bool | `false` | Count DB queries executed per request. |
| `query_threshold`      | int  | `20`    | Trigger `X-Performance-Warning` when query count exceeds threshold. |
| `slow_request_threshold` | int | `1000` | Milliseconds threshold for slow warning. |

### 4.3 ETag

| Key             | Type | Default | Description |
|-----------------|------|---------|-------------|
| `etag_algorithm`| enum | `md5`   | Hash algorithm (`md5`, `sha1`, `sha256`). |
| `etag_max_age`  | int  | `300`   | Seconds for `Cache-Control` max-age. |

### 4.4 Security Headers

| Key                    | Type   | Default                               | Description |
|------------------------|--------|---------------------------------------|-------------|
| `referrer_policy`      | string | `strict-origin-when-cross-origin`     | Controls `Referrer-Policy`. |
| `hsts_max_age`         | int    | `31536000`                            | Seconds for HSTS. Set to `0` to disable. |
| `hsts_include_subdomains` | bool | `false`                               | Adds `includeSubDomains`. |
| `content_security_policy` | string | `default-src 'none'; frame-ancestors 'none'` | Baseline CSP string. |
| `permissions_policy`   | string | `geolocation=(), microphone=(), camera=()` | Restrict browser capabilities. |

### 4.5 Cache

See `docs/API-CACHE.md` for design details.

| Key                    | Type  | Default | Description |
|------------------------|-------|---------|-------------|
| `enabled`              | bool  | `false` | Master toggle for response caching. |
| `driver`               | string| `redis` | Cache store (must support tags for best results). |
| `ttl`                  | int   | `300`   | Cache lifetime in seconds. |
| `per_user`             | bool  | `false` | Include authenticated user id in cache key. |
| `cache_authenticated`  | bool  | `false` | Allow caching of authenticated requests. |
| `track_metrics`        | bool  | `true`  | Maintain hit/miss counters in memory. |
| `vary_headers`         | array | `[]`    | Headers that influence cache key (e.g., `Accept-Language`). |
| `cacheable_content_types` | array | JSON/XML list | MIME types eligible for caching. |
| `purge_methods`        | array | `['POST','PUT','PATCH','DELETE']` | Mutation verbs triggering invalidation. |
| `dynamic_tagging.enabled` | bool | `true` | Enable automatic tag derivation from path segments. |
| `dynamic_tagging.ignore_segments` | array | `['api']` | Segments to omit from tag derivation. |
| `dynamic_tagging.normalize_ids` | bool | `true` | Replace numeric segments with `{id}` placeholder. |
| `dynamic_tagging.max_depth` | int | `5` | Maximum depth for generated tags. |

### 4.6 Health Check

| Key              | Type | Default | Description |
|------------------|------|---------|-------------|
| `endpoint`       | string | `/health` | Route path served by `ApiHealthCheck`. |
| `cache_results`  | bool | `true`  | Cache probe results for ~10 seconds to reduce load. |
| `include_app_info` | bool | `true` | Attach app name/version metadata to response. |
| `checks.database` | bool | `true` | Ping default DB connection and measure latency. |
| `checks.cache`    | bool | `true` | Probe configured cache store. |
| `checks.disk`     | bool | `true` | Report disk usage via `disk_free_space`. |
| `checks.memory`   | bool | `true` | Provide memory usage snapshot. |
| `checks.queue`    | bool | `false` | Check queue connectivity (disabled by default). |
| `thresholds.database_ms` | int | `100` | Max DB ping in ms before warning. |
| `thresholds.cache_ms` | int | `50` | Max cache ping in ms before warning. |
| `thresholds.disk_usage_percent` | int | `90` | Disk usage warning threshold. |
| `thresholds.memory_usage_percent` | int | `90` | Memory usage warning threshold. |

### 4.7 Circuit Breaker

| Key                  | Type | Default | Description |
|----------------------|------|---------|-------------|
| `enabled`            | bool | `false` | Toggle circuit breaker middleware. |
| `failure_threshold`  | int  | `5`     | Number of failures before opening circuit. |
| `timeout`            | int  | `60`    | Seconds before transitioning to half-open. |
| `scope`              | enum | `endpoint` | Aggregation key: `endpoint`, `route`, or `path`. |
| `slow_threshold_ms`  | int  | `5000`  | Classify long-running requests as failures. |
| `error_codes`        | array| `[500,502,503,504]` | Status codes counted as failures. |
| `fallback_status_code` | int | `503`  | Returned while circuit is open. |
| `fallback_response`  | mixed| `null`  | Closure/callable to generate custom fallback body. |

---

## 5. How to Use

1. **Publish once** using `php artisan vendor:publish --provider="VinkiusLabs\\LaravelPageSpeed\\ServiceProvider"`.
2. **Version control the file** to keep configuration auditable across environments.
3. **Leverage environment variables** to toggle features per deployment (e.g., disable cache in staging by default).
4. **Group related overrides** in custom config files if your application uses multiple API stacks (e.g., `config/page-speed-tenant.php`).
5. **Document opt-outs** for teams: when a route is added to `skip`, annotate the reason so future reviewers understand the trade-off.

---

## 6. When to Use

- **Enable globally** when the application is primarily SSR or API driven and performance regressions are traceable via telemetry.
- **Enable selectively** (per route group) for legacy areas until HTML sanitization or caching compatibility is verified.
- **Disable temporarily** during incident response or when debugging payloads that are easier to inspect unminified/uncompressed.
- **Turn on caching** once Redis/Memcached clusters are sized for additional load and eviction policies are validated with business stakeholders.
- **Activate circuit breaker** for endpoints that depend on flaky third-party services to prevent cascading failures.
- **Expose health endpoints** when integrating with load balancers, Kubernetes, or serverless orchestrators requiring readiness probes.

Refer back to this document whenever you revise operational requirements or add new services to ensure the configuration remains aligned with SLAs.
