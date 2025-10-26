statsd.histogram('api.response_time', responseTime);
# Scenario Playbooks for API Optimization

Curated configurations that show how Laravel Page Speed can be embedded into different API domains. Each playbook provides baseline metrics, recommended middleware combinations, and validation steps you can replicate in staging.

---

## Table of Contents

- [1. E-commerce Catalogue API](#1-e-commerce-catalogue-api)
- [2. SaaS Multi-tenant API](#2-saas-multi-tenant-api)
- [3. Mobile Backend for Fintech](#3-mobile-backend-for-fintech)
- [4. Microservices Gateway](#4-microservices-gateway)
- [5. Observability Recipes](#5-observability-recipes)

---

## 1. E-commerce Catalogue API

**Context:** Product and pricing data served to storefronts and partner marketplaces. High read/write asymmetry (90% GET).

| Metric                         | Baseline        | Optimized (cache hit) | Delta |
|--------------------------------|-----------------|-----------------------|-------|
| Payload size (JSON, 100 items) | 15.2 KB         | 2.8 KB                | -82%  |
| Response latency               | 430 ms          | 12 ms                 | -97%  |
| SQL queries                    | 35              | 0                     | -100% |
| Cache hit rate                 | 0%              | 65%                   | +65pp |

**Recommended middleware order:**

```php
\VinkiusLabs\LaravelPageSpeed\Middleware\ApiSecurityHeaders::class,
\VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCache::class,
\VinkiusLabs\LaravelPageSpeed\Middleware\ApiETag::class,
\VinkiusLabs\LaravelPageSpeed\Middleware\ApiResponseCompression::class,
\VinkiusLabs\LaravelPageSpeed\Middleware\ApiPerformanceHeaders::class,
```

**Configuration highlights:**

```env
API_CACHE_ENABLED=true
API_CACHE_DRIVER=redis
API_CACHE_TTL=300
API_CACHE_DYNAMIC_TAGS=true
API_CACHE_PURGE_METHODS="POST,PUT,PATCH,DELETE"
API_MIN_COMPRESSION_SIZE=1024
API_SHOW_COMPRESSION_METRICS=true
```

**Validation checklist:**
- Run k6 load test with 200 virtual users hitting `/api/catalogue?page=1`.
- Validate `X-Cache-Status` transitions from `MISS` to `HIT` after warm-up.
- Confirm `api.products.update` controller triggers cache invalidation via mutation tests in `tests/Middleware/ApiResponseCacheTest.php`.
- Assert merchandising dashboards still receive fresh data (cache TTL remains short).

---

## 2. SaaS Multi-tenant API

**Context:** CRM-style SaaS with tenant isolation and per-user data. Read/write ratio balanced, and regulatory requirements demand strict security headers.

| Metric                          | Baseline | Optimized | Notes                                   |
|---------------------------------|----------|-----------|-----------------------------------------|
| Bandwidth per tenant/day        | 1.2 GB   | 410 MB    | -66% via compression + caching          |
| Error budget consumption        | 2.4%     | 0.6%      | Circuit breaker prevented cascading failures |
| Security compliance checklist   | 68%      | 100%      | Automated headers satisfied PCI/SOC2    |

**Key configuration:**

```env
API_CACHE_ENABLED=true
API_CACHE_PER_USER=true
API_CACHE_AUTHENTICATED=true
API_CIRCUIT_BREAKER_ENABLED=true
API_CIRCUIT_BREAKER_THRESHOLD=5
API_CIRCUIT_BREAKER_TIMEOUT=60
API_CIRCUIT_BREAKER_SCOPE=route
```

**Testing approach:**
- Use PHPUnit feature tests with Sanctum tokens to assert cache segmentation by tenant/user id.
- Simulate downstream CRM outage using Laravel’s HTTP client fakes and verify `fallback_status_code` is returned with `X-Circuit-Breaker-State: open`.
- Run OWASP ZAP or Burp to validate CSP/HSTS headers align with compliance requirements.

---

## 3. Mobile Backend for Fintech

**Context:** Mobile clients pulling account snapshots every 30 seconds. Payloads are small but frequent, so composite caching and aggressive compression may not help.

| Decision point                          | Recommendation                                    |
|-----------------------------------------|---------------------------------------------------|
| Compression for <512B payloads          | Disable (`API_MIN_COMPRESSION_SIZE=2048`)         |
| Health endpoint                         | Enable `/health` with disk + DB metrics           |
| Cache strategy                          | Keep disabled, rely on ETags for revalidation     |
| Circuit breaker                         | Enable with `error_codes=[500, 502, 503, 504]`    |

**Sample response headers after tuning:**

```
HTTP/1.1 200 OK
Content-Type: application/json
ETag: "w/\"txn-20251025-87\""
Cache-Control: private, max-age=60, must-revalidate
X-Response-Time: 28.75ms
X-Request-ID: 20251025-fn-8c1d
X-Circuit-Breaker-State: closed
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

**Verification:**
- Instrument API Gateway / ALB to ensure `If-None-Match` is propagated downstream.
- Validate that device clients gracefully handle `304 Not Modified` (reduces over-the-air usage without local cache divergence).
- Monitor `X-Circuit-Breaker-State` via Grafana to catch early degradation of third-party payment processors.

---

## 4. Microservices Gateway

**Context:** A BFF (Backend for Frontend) aggregating multiple upstream microservices. Latency is dominated by upstream calls, and observability is paramount.

**Middleware emphasis:**

- Enable `ApiPerformanceHeaders` and propagate `X-Request-ID` to downstream services.
- Use `ApiResponseCache` selectively on aggregation endpoints (e.g., `/api/dashboard`) with short TTL (15 seconds).
- Pair with `ApiResponseCompression` to control response size when bundling multiple upstream payloads.

**Example aggregator controller:**

```php
public function show(DashboardAggregator $aggregator)
{
    $response = $aggregator->hydrate(request()->user());

    return response()->json($response)
        ->header('X-Upstream-Latency', $aggregator->latency());
}
```

`X-Upstream-Latency` can be combined with `X-Response-Time` to isolate gateway overhead vs upstream cost.

**Monitoring plan:**
- Install Envoy/Service Mesh filters that forward `X-Request-ID` into Jaeger/OpenTelemetry traces.
- Alert when `X-Performance-Warning` appears more than 5 times within five minutes for a given route.

---

## 5. Observability Recipes

### Datadog

```javascript
const responseTime = parseFloat(response.headers['x-response-time']);
const cacheStatus = response.headers['x-cache-status'] ?? 'MISS';
const breakerState = response.headers['x-circuit-breaker-state'] ?? 'closed';

statsd.histogram('api.response_time', responseTime, [`cache:${cacheStatus}`, `breaker:${breakerState}`]);
```

### New Relic Browser / SPA

```javascript
newrelic.addCustomAttribute('cache_status', cacheStatus);
newrelic.addCustomAttribute('request_id', response.headers['x-request-id']);
newrelic.recordMetric('Custom/API/ResponseTime', responseTime);
```

### Prometheus (via nginx ingress annotations)

```
nginx.ingress.kubernetes.io/configuration-snippet: |
  more_set_headers "X-Cache-Status: $upstream_http_x_cache_status";
  more_set_headers "X-Response-Time: $upstream_http_x_response_time";
```

Tie these headers to Grafana panels to surface cache efficiency, compression ratio, and circuit breaker state trends.

---

These playbooks provide repeatable recipes. Start from the scenario closest to your workload, run the validation checklist in staging, and only then roll out to production with feature flags for quick rollback.
