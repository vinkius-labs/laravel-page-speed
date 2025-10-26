# API Cache Architecture

Detailed reference for the response cache subsystem powering `ApiResponseCache`.

---

## Table of Contents

- [1. Goals and Constraints](#1-goals-and-constraints)
- [2. Data Flow](#2-data-flow)
- [3. Dynamic Tagging Model](#3-dynamic-tagging-model)
- [4. Configuration Surface](#4-configuration-surface)
- [5. Failure Modes and Fallbacks](#5-failure-modes-and-fallbacks)
- [6. Validation Plan](#6-validation-plan)
- [7. Implementation Notes](#7-implementation-notes)

---

## 1. Goals and Constraints

- Serve idempotent requests (GET/HEAD) from cache without mutating payloads.
- Invalidate cached entries deterministically on mutations (POST/PUT/PATCH/DELETE) that target equivalent resource segments.
- Support per-user segmentation for authenticated APIs.
- Avoid cache stampede and stale data by leveraging tag indices rather than brute-force clears.
- Remain driver-agnostic: Redis and Memcached fully supported; file/array driver limited (no tagging).

---

## 2. Data Flow

1. **Request qualification**
   - Skips cache unless `cache.enabled` is `true`.
   - Evaluates HTTP method (`cacheable` defaults to GET/HEAD) and content type (`cacheable_content_types`).
   - Optionally checks authentication when `cache_authenticated` is `false`.

2. **Key derivation**
   - Base key: SHA-1 hash of method + normalized URI + per-user token (optional) + vary headers.
   - Per-request metadata stored alongside payload: status code, headers, ETag, compression flag.

3. **Tag assignment**
   - Tags derived from path segments (see [Dynamic Tagging Model](#3-dynamic-tagging-model)).
   - Custom tags can be appended via request attribute `page_speed.cache.tags`.

4. **Response storage**
   - Cache entry stores serialized body + metadata + tag list.
   - TTL defaults to `cache.ttl` seconds.

5. **Invalidation**
   - Mutation verbs gather matching tags and flush them from the tag index.
   - Tag index resides in cache as `pagespeed:tag:{tag}` containing key IDs.

---

## 3. Dynamic Tagging Model

- **Segment parsing**
  - Routes are split on `/`, filtered by `dynamic_tagging.ignore_segments` (default `["api"]`).
  - Numeric segments optionally normalized to a placeholder when `normalize_ids=true`.

- **Depth control**
  - Only the first `max_depth` segments are considered (default `5`).
  - Example: `/api/v1/customers/42/invoices/3` yields tags
    - `api:v1`
    - `api:v1:customers`
    - `api:v1:customers:{id}`
    - `api:v1:customers:{id}:invoices`

- **Custom augmentation**
  - Controllers may push tags via `app('pagespeed.cache')->tag('tenant:'.$tenantId);`

- **Mutation invalidation**
  - A `POST /api/v1/customers/42/invoices` clears all tags prefixed with the normalized path, ensuring collection endpoints (e.g., `/api/v1/customers/42/invoices?page=2`) are refreshed.

---

## 4. Configuration Surface

Key options from `config/laravel-page-speed.php` (see `docs/CONFIGURATION.md` for full context):

```php
'api' => [
    'cache' => [
        'enabled' => env('API_CACHE_ENABLED', false),
        'driver' => env('API_CACHE_DRIVER', 'redis'),
        'ttl' => env('API_CACHE_TTL', 300),
        'per_user' => env('API_CACHE_PER_USER', false),
        'cache_authenticated' => env('API_CACHE_AUTHENTICATED', false),
        'track_metrics' => env('API_CACHE_TRACK_METRICS', true),
        'vary_headers' => [],
        'cacheable_content_types' => ['application/json', 'application/xml', 'application/vnd.api+json'],
        'purge_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        'dynamic_tagging' => [
            'enabled' => env('API_CACHE_DYNAMIC_TAGS', true),
            'ignore_segments' => ['api'],
            'normalize_ids' => true,
            'max_depth' => 5,
        ],
    ],
];
```

---

## 5. Failure Modes and Fallbacks

| Scenario                                 | Behaviour                                                              | Mitigation                                                          |
|------------------------------------------|------------------------------------------------------------------------|----------------------------------------------------------------------|
| Cache backend unavailable                 | Middleware bypasses cache and continues request lifecycle              | Use monitoring on cache pool; configure retry/backoff in driver.     |
| Tag index rebuild required                | Mutation clears only known keys; orphaned keys may remain temporarily  | Schedule periodic background sweep using Laravel command `pagespeed:cache:prune`. |
| Large payload serialization overhead      | Response stored as UTF-8 string (binary-safe). Compression still applied downstream | Increase TTL to reduce churn; consider upstream pagination.          |
| Per-user cache explosion                  | `per_user=true` multiplies key cardinality                             | Combine with lower TTL and targeted tagging to control footprint.    |

---

## 6. Validation Plan

1. **Unit tests** – `tests/Middleware/ApiResponseCacheTest.php` covers hits, misses, purge verbs, nested paths, and per-user tagging.
2. **Integration tests** – create end-to-end scenarios using Laravel HTTP tests with `Cache::store('redis')` configured.
3. **Load test** – measure miss vs hit latency using k6: ensure p95 latency stays <30 ms on hits.
4. **Chaos drill** – disable Redis for a short window; verify app serves uncached responses without fatal errors.

---

## 7. Implementation Notes

- The cache wrapper uses Laravel's Cache tagging API; drivers without tagging automatically degrade to key-based storage but lose purge precision.
- `X-Cache-Status` header advertises `MISS`, `HIT`, or `BYPASS` for observability. Pair with `X-Cache-Store` to confirm driver.
- Metrics (if enabled) track hit/miss counts in memory; integrate with Prometheus via custom collectors if needed.
- TTL should align with business SLAs—set shorter durations for mutable data, longer for catalogs/reference lists.

Use this document alongside `docs/API-OPTIMIZATION.md` to design cache rollouts that meet correctness and performance targets.
