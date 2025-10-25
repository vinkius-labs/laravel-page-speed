# 📚 Laravel Page Speed - Documentation

Complete documentation for optimizing both web pages and REST APIs.

---

## 📖 Documentation Guides

### 🌐 Web Optimization (HTML/Blade)
**[→ Read the Web Optimization Guide](WEB-OPTIMIZATION.md)**

Complete guide for optimizing HTML pages, Blade templates, and web applications.

**Topics covered:**
- HTML minification and whitespace removal
- CSS inlining and optimization
- JavaScript deferral for non-blocking execution
- DNS prefetching for faster resource loading
- Attribute elision and quote removal
- Livewire & Filament compatibility
- Debug tools integration (Debugbar, Telescope, Horizon)
- Performance benchmarks and best practices
- Troubleshooting common issues

**Performance gains:** 35%+ reduction in page size, 33% faster First Paint

---

### ⚡ API Optimization (REST/JSON)
**[→ Read the API Optimization Guide](API-OPTIMIZATION.md)**

Advanced features for optimizing REST APIs with caching, compression, and resilience patterns.

**Topics covered:**
- Response caching with Redis/Memcached
- Smart compression (Brotli/Gzip)
- ETag support for 304 Not Modified responses
- Security headers (HSTS, CSP, XSS protection)
- Performance metrics headers
- Circuit breaker pattern for resilience
- Health checks for Kubernetes
- Cache invalidation strategies
- Configuration and best practices

**Performance gains:** 60-85% bandwidth savings, 99.6% faster responses with cache

---

### 📙 Real-World Examples
**[→ Read Examples & Use Cases](API-EXAMPLES.md)**

Practical examples showing before/after comparisons and real-world implementation scenarios.

**Topics covered:**
- E-commerce platform optimization
- Mobile API backends
- High-traffic applications
- Microservices patterns
- Cost savings analysis
- Monitoring and observability
- Production deployment strategies

---

## 🚀 Quick Links

### Getting Started
- [Installation Guide](../README.md#-installation)
- [Quick Start - Web Optimization](../README.md#for-web-pages-bladehtml)
- [Quick Start - API Optimization](../README.md#for-rest-apis)

### Configuration
- [Environment Variables](API-OPTIMIZATION.md#environment-variables)
- [Skip Routes Configuration](WEB-OPTIMIZATION.md#skip-routes-configuration)
- [Middleware Order](WEB-OPTIMIZATION.md#recommended-middleware-order)

### Advanced Topics
- [Custom Middleware](WEB-OPTIMIZATION.md#custom-middleware)
- [Cache Invalidation](API-OPTIMIZATION.md#cache-invalidation)
- [Circuit Breaker Tuning](API-OPTIMIZATION.md#circuit-breaker-configuration)
- [Health Check Integration](API-OPTIMIZATION.md#health-check-configuration)

---

## 📊 Performance Overview

### Web Pages (HTML/Blade)
```
Before: 245 KB, 1.8s First Paint
After:  159 KB, 1.2s First Paint
Improvement: -35% size, -33% render time
```

### REST APIs (JSON/XML)
```
Before: 15.2 KB, 450ms response time
After:  2.8 KB, 2ms response time (cache hit)
Improvement: -82% bandwidth, -99.6% response time
```

---

## 🎯 Use Case Navigator

### I want to optimize...

#### Web Pages
- **HTML output** → [CollapseWhitespace](WEB-OPTIMIZATION.md#collapsewhitespace)
- **CSS delivery** → [InlineCss](WEB-OPTIMIZATION.md#inlinecss)
- **JavaScript loading** → [DeferJavascript](WEB-OPTIMIZATION.md#deferjavascript)
- **External resources** → [InsertDNSPrefetch](WEB-OPTIMIZATION.md#insertdnsprefetch)

#### REST APIs
- **Bandwidth usage** → [ApiResponseCompression](API-OPTIMIZATION.md#apiresponsecompression)
- **Response time** → [ApiResponseCache](API-OPTIMIZATION.md#apiresponsecache)
- **Caching** → [ApiETag](API-OPTIMIZATION.md#apietag)
- **Security** → [ApiSecurityHeaders](API-OPTIMIZATION.md#apisecurityheaders)
- **Monitoring** → [ApiPerformanceHeaders](API-OPTIMIZATION.md#apiperformanceheaders)
- **Resilience** → [ApiCircuitBreaker](API-OPTIMIZATION.md#apicircuitbreaker)
- **Health checks** → [ApiHealthCheck](API-OPTIMIZATION.md#apihealthcheck)

---

## 🔧 Troubleshooting

Having issues? Check these guides:

- **Web Optimization Issues** → [Troubleshooting Section](WEB-OPTIMIZATION.md#troubleshooting)
- **API Cache Not Working** → [Cache Configuration](API-OPTIMIZATION.md#configuration)
- **Livewire Compatibility** → [Compatibility](WEB-OPTIMIZATION.md#-compatible-frameworks)
- **Debug Tools** → [Debug Tools Compatibility](WEB-OPTIMIZATION.md#-compatible-debug-tools)

---

## 🧪 Testing

All features are thoroughly tested with **189 tests** and **762 assertions**.

```bash
composer test
```

**Test coverage includes:**
- Unit tests for all middlewares
- Chaos engineering scenarios
- Circuit breaker state transitions
- Cache hit/miss scenarios
- Concurrent request handling
- High load simulations

---

## 📧 Support

- 🐛 **Issues**: [GitHub Issues](https://github.com/vinkius-labs/laravel-page-speed/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/vinkius-labs/laravel-page-speed/discussions)
- 📧 **Email**: renato.marinho@s2move.com
- ⭐ **Star us**: [GitHub Repository](https://github.com/vinkius-labs/laravel-page-speed)

---

## 🎉 Version History

### Latest Features
- ✅ **7 new API optimization middlewares**
- ✅ **Response caching** with Redis/Memcached
- ✅ **Circuit breaker pattern** for resilience
- ✅ **Health checks** for Kubernetes
- ✅ **Smart compression** (Brotli/Gzip)
- ✅ **Performance metrics** headers
- ✅ **Security headers** (HSTS, CSP, XSS)

See [CHANGELOG.md](../CHANGELOG.md) for complete version history.

---

<p align="center">
    <strong>Made with ❤️ by VinkiusLabs</strong>
</p>

<p align="center">
    <a href="../README.md">← Back to Main README</a>
</p>
