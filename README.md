<p align="center">
    <img width="420" src="https://raw.githubusercontent.com/vinkius-labs/laravel-page-speed/master/art/logo.png" alt="Laravel Page Speed" />
</p>

<p align="center">
    <a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://img.shields.io/packagist/v/vinkius-labs/laravel-page-speed?style=flat-square" alt="Latest Version"></a>
    <a href="https://packagist.org/packages/renatomarinho/laravel-page-speed"><img src="https://img.shields.io/packagist/dt/renatomarinho/laravel-page-speed?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://img.shields.io/packagist/dd/vinkius-labs/laravel-page-speed?style=flat-square" alt="Daily Downloads"></a>
    <a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://img.shields.io/packagist/l/vinkius-labs/laravel-page-speed?style=flat-square" alt="License"></a>
    <a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://img.shields.io/github/stars/vinkius-labs/laravel-page-speed?style=flat-square" alt="GitHub Stars"></a>
    <a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://img.shields.io/packagist/php-v/vinkius-labs/laravel-page-speed?style=flat-square" alt="PHP Version"></a>
</p>

# Laravel Page Speed

Laravel Page Speed delivers an end-to-end optimization pipeline for Blade-rendered pages and REST APIs with measurable gains in latency, bandwidth, and resiliency.

## Table of Contents

- [Overview](#overview)
- [Optimization Pipelines](#optimization-pipelines)
- [Quick Integration](#quick-integration)
- [Measured Impact](#measured-impact)
- [Observability and Resilience](#observability-and-resilience)
- [Documentation Suite](#documentation-suite)
- [Contributing and Support](#contributing-and-support)

## Overview

- **Dual scope**: optimizes rendered HTML and JSON/XML payloads without modifying your business payloads.
- **Composable stack**: enable only the middleware you need through `config/laravel-page-speed.php`.
- **Store-agnostic**: works with Redis, Memcached, DynamoDB (via cache tags), file, or array drivers across Laravel 10–13.
- **Built for observability**: exposes latency, memory usage, cache hits, and circuit status via standard HTTP headers.

## Optimization Pipelines

### Web (HTML/Blade)

- Structured HTML minification and comment stripping that stay compatible with Bootstrap, Tailwind, and Livewire.
- Targeted critical CSS inlining to reduce render-blocking round trips.
- Script deferral and DNS prefetching that maintain execution order through `data-ps-*` guards.

### APIs (REST/JSON)

- Adaptive compression (Brotli first, Gzip fallback) with configurable size thresholds to avoid overhead on small payloads.
- Response caching with method-aware invalidation, dynamic tag derivation per path segment, and hit-rate metrics.
- Pre-hardened security headers (HSTS, CSP, Permissions-Policy) and an automatic circuit breaker with customizable fallbacks.
- Lightweight health check middleware designed for Kubernetes probes and service orchestrators.

## Quick Integration

### Web Middleware

Choose the registration pattern that matches your Laravel install:

**Laravel 10.x (`app/Http/Kernel.php`)**

Append the middleware inside the `web` group so the order stays deterministic:

```php
protected $middlewareGroups = [
    'web' => [
        // ... existing middleware
        \VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\ElideAttributes::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace::class,
        \VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript::class,
    ],
];
```

**Laravel 11.x, 12.x and 13.x (`bootstrap/app.php`)**

Use the middleware configurator introduced in Laravel 11. Extend the existing `->withMiddleware` closure:

```php
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: __DIR__.'/../')
    // ... existing configuration
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', [
            \VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss::class,
            \VinkiusLabs\LaravelPageSpeed\Middleware\ElideAttributes::class,
            \VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch::class,
            \VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace::class,
            \VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript::class,
        ]);

        // keep other group definitions (api, broadcast, etc.) here
    })
    ->create();
```

### API Middleware

Attach only the middleware that fits your API architecture.

**Laravel 10.x (`app/Http/Kernel.php`)**

```php
protected $middlewareGroups = [
    'api' => [
        // ... existing middleware
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

**Laravel 11.x, 12.x and 13.x (`bootstrap/app.php`)**

Inside the same `->withMiddleware` closure from the Web section, append the API stack:

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

### Publish assets and baseline environment variables

```bash
composer require vinkius-labs/laravel-page-speed
php artisan vendor:publish --provider="VinkiusLabs\\LaravelPageSpeed\\ServiceProvider"
```

Recommended baseline for cached APIs:

```env
LARAVEL_PAGE_SPEED_ENABLE=true
API_CACHE_ENABLED=true
API_CACHE_DRIVER=redis
API_CACHE_TTL=300
API_CACHE_DYNAMIC_TAGS=true
```

## Measured Impact

| Metric                       | Before  | After (cache hit) | Delta  |
| ---------------------------- | ------- | ----------------- | ------ |
| Page Size (Blade)            | 245 KB  | 159 KB            | -35%   |
| First Paint                  | 1.8 s   | 1.2 s             | -33%   |
| API Payload                  | 15.2 KB | 2.8 KB            | -82%   |
| Average API Latency          | 450 ms  | 2 ms              | -99.6% |
| SQL Queries (100-item list)  | 35      | 0                 | -100%  |
| Monthly Bandwidth (estimate) | 15 TB   | 3 TB              | -80%   |

Reference scenario: 1M requests/day with a 65% cache hit rate.

## Observability and Resilience

- **Performance headers**: `X-Response-Time`, `X-Memory-Usage`, `X-Cache-Status`, `X-Circuit-Breaker-State` ready for ingestion by Datadog, New Relic, or Prometheus scrapers.
- **Configurable circuit breaker**: customize failure thresholds, timeout, and scope (route, endpoint, or path) via configuration.
- **Adaptive health check**: aggregates database, cache, disk, and queue probes with optional 10-second result caching.
- **Debug-aware skipping**: curated `skip` patterns avoid instrumenting Debugbar, Telescope, Horizon, or custom diagnostic routes.

## Documentation Suite

- [Documentation Hub](docs/README.md)
- [Web Optimization](docs/WEB-OPTIMIZATION.md)
- [API Optimization](docs/API-OPTIMIZATION.md)
- [Cache Architecture](docs/API-CACHE.md)
- [Configuration Reference](docs/CONFIGURATION.md)
- [Scenario Playbooks](docs/API-EXAMPLES.md)
- [Executive Summary](docs/PACKAGE-SUMMARY.md)

## Contributing and Support

- Review [CONTRIBUTING.md](CONTRIBUTING.md) before opening pull requests.
- Run `composer test` (or `docker compose exec app vendor/bin/phpunit`) prior to submitting changes.
- File issues and start discussions via [GitHub Issues](../../issues) and [Discussions](../../discussions).
- Distributed under the [MIT license](LICENSE.md).
