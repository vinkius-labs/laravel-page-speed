# 🎯 API Optimization Examples - Before & After

This document shows real-world examples of API responses before and after applying Laravel Page Speed API middlewares.

## Example 1: Product List API

### Before Laravel Page Speed

**Request:**
```http
GET /api/products HTTP/1.1
Host: api.example.com
Accept: application/json
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json
Content-Length: 15234

[
  {
    "id": 1,
    "name": "Professional Camera Kit",
    "description": "High-quality camera with multiple lenses and accessories for professional photography",
    "price": 1299.99,
    "category": "Electronics",
    "stock": 45,
    "images": [
      "https://cdn.example.com/images/camera-1.jpg",
      "https://cdn.example.com/images/camera-2.jpg"
    ],
    "specifications": {
      "megapixels": 24.2,
      "sensor": "Full Frame CMOS",
      "iso_range": "100-51200"
    }
  },
  // ... 99 more products
]
```

**Issues:**
- ❌ No compression (15.2 KB transferred)
- ❌ No caching (same data transferred every time)
- ❌ No security headers
- ❌ No performance metrics
- ❌ No way to trace slow requests

---

### After Laravel Page Speed

**Request:**
```http
GET /api/products HTTP/1.1
Host: api.example.com
Accept: application/json
Accept-Encoding: gzip, br
```

**Response (First Request):**
```http
HTTP/1.1 200 OK
Content-Type: application/json
Content-Encoding: br
Content-Length: 2847
ETag: "5d41402abc4b2a76b9719d911017c592"
Cache-Control: private, max-age=300, must-revalidate
Vary: Accept-Encoding

X-Response-Time: 234.56ms
X-Memory-Usage: 2.34 MB
X-Query-Count: 3
X-Request-ID: 20251025142530-a3f9c2d1
X-Original-Size: 15234
X-Compressed-Size: 2847
X-Compression-Savings: 81.31%

X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'none'; frame-ancestors 'none'
Permissions-Policy: geolocation=(), microphone=(), camera=()

[compressed binary data]
```

**Benefits:**
- ✅ **81% smaller** (2.8 KB vs 15.2 KB)
- ✅ **Cached** (ETag for future requests)
- ✅ **7 security headers** added
- ✅ **Performance metrics** visible
- ✅ **Request tracing** enabled

---

**Second Request (Cached):**
```http
GET /api/products HTTP/1.1
Host: api.example.com
Accept: application/json
If-None-Match: "5d41402abc4b2a76b9719d911017c592"
```

**Response:**
```http
HTTP/1.1 304 Not Modified
ETag: "5d41402abc4b2a76b9719d911017c592"
X-Response-Time: 12.34ms
X-Request-ID: 20251025142545-b7e8f3a2

(no body - client uses cached data)
```

**Benefits:**
- ✅ **0 bytes transferred** (304 response)
- ✅ **95% faster** (12ms vs 234ms)
- ✅ **Server CPU saved**

---

## Example 2: User Profile API

### Before Laravel Page Speed

**Request:**
```http
GET /api/users/123 HTTP/1.1
Host: api.example.com
```

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json
Content-Length: 856

{
  "id": 123,
  "name": "John Doe",
  "email": "john@example.com",
  "profile": {
    "bio": "Software developer passionate about clean code and performance optimization",
    "avatar": "https://cdn.example.com/avatars/john-doe.jpg",
    "location": "San Francisco, CA",
    "website": "https://johndoe.dev"
  },
  "stats": {
    "followers": 1234,
    "following": 567,
    "posts": 89
  },
  "created_at": "2020-01-15T10:30:00Z",
  "updated_at": "2025-10-24T15:45:00Z"
}
```

---

### After Laravel Page Speed

**Response:**
```http
HTTP/1.1 200 OK
Content-Type: application/json
Content-Encoding: gzip
Content-Length: 387
ETag: "3c9d3c4e8f9a2b1d7e6f5a4c3b2a1e0d"
Cache-Control: private, max-age=300, must-revalidate

X-Response-Time: 45.23ms
X-Memory-Usage: 512.00 KB
X-Query-Count: 4
X-Request-ID: 20251025143000-c4d5e6f7

X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Strict-Transport-Security: max-age=31536000

[compressed data]
```

**Improvements:**
- 📉 **55% smaller** (387 bytes vs 856 bytes)
- 🔒 **Secure** (HSTS, XSS protection, etc.)
- 📊 **Observable** (4 DB queries detected)
- ⚡ **Cacheable** (5 min cache)

---

## Example 3: Slow Endpoint Detection

### API with N+1 Query Problem

**Request:**
```http
GET /api/orders?include=customer,items HTTP/1.1
Host: api.example.com
```

**Response Headers:**
```http
X-Response-Time: 3245.67ms
X-Memory-Usage: 15.23 MB
X-Query-Count: 87
X-Performance-Warning: High query count detected
X-Request-ID: 20251025143100-d7e8f9a0
```

**What This Tells You:**
- ⚠️ **87 database queries** - N+1 problem!
- ⚠️ **3.2 seconds** - Very slow!
- ⚠️ **15 MB memory** - High usage
- 🔍 **Request ID** for log tracing

**Action:** Fix eager loading:
```php
// Before (causing N+1)
Order::all()->load('customer', 'items');

// After (fixed)
Order::with('customer', 'items')->get();
```

**After Fix:**
```http
X-Response-Time: 145.32ms
X-Memory-Usage: 2.45 MB
X-Query-Count: 3
X-Request-ID: 20251025143200-e8f9a0b1
```

**Results:**
- ✅ **95% faster** (145ms vs 3245ms)
- ✅ **84% less memory** (2.4 MB vs 15.2 MB)
- ✅ **97% fewer queries** (3 vs 87)

---

## Example 4: Error Response

### Before Laravel Page Speed

**Request:**
```http
GET /api/users/999999 HTTP/1.1
Host: api.example.com
```

**Response:**
```http
HTTP/1.1 404 Not Found
Content-Type: application/json
Content-Length: 87

{
  "error": "User not found",
  "message": "The requested user does not exist",
  "code": 404
}
```

---

### After Laravel Page Speed

**Response:**
```http
HTTP/1.1 404 Not Found
Content-Type: application/json
Content-Length: 87

X-Response-Time: 12.45ms
X-Memory-Usage: 256.00 KB
X-Query-Count: 1
X-Request-ID: 20251025143300-f9a0b1c2

X-Content-Type-Options: nosniff
X-Frame-Options: DENY

{
  "error": "User not found",
  "message": "The requested user does not exist",
  "code": 404
}
```

**Notes:**
- ✅ Error response **NOT compressed** (too small, optional config)
- ✅ Still has **security headers**
- ✅ Still has **performance metrics**
- ✅ **Request ID** for debugging

---

## Real-World Performance Impact

### High-Traffic API (1M requests/day)

| Metric | Before | After | Savings |
|--------|--------|-------|---------|
| **Bandwidth** | 15 TB/day | 3 TB/day | **80% reduction** |
| **Avg Response Time** | 450ms | 180ms | **60% faster** |
| **Cache Hit Rate** | 0% | 65% | **65% fewer DB queries** |
| **Server CPU** | 85% avg | 45% avg | **47% less CPU** |
| **Monthly Bandwidth Cost** | $450 | $90 | **$360 saved** |

### API Monitoring Dashboard Integration

Use the headers with your monitoring tools:

**DataDog:**
```javascript
// Extract metrics from response headers
const responseTime = parseFloat(response.headers['x-response-time']);
const queryCount = parseInt(response.headers['x-query-count']);
const requestId = response.headers['x-request-id'];

statsd.histogram('api.response_time', responseTime);
statsd.gauge('api.query_count', queryCount);
```

**New Relic:**
```javascript
newrelic.addCustomAttribute('request_id', requestId);
newrelic.addCustomAttribute('query_count', queryCount);
newrelic.recordMetric('Custom/API/ResponseTime', responseTime);
```

---

## Summary

Laravel Page Speed API middlewares provide:

1. **Massive Bandwidth Savings** (60-85% reduction)
2. **Faster Responses** (caching + compression)
3. **Better Security** (7+ headers automatically)
4. **Full Observability** (metrics for every request)
5. **Zero Code Changes** (just add middleware)
6. **Production Ready** (tested with 149 unit tests)

**And most importantly: Your API data is NEVER modified!** ✅

---

**Ready to optimize your APIs?** [Get Started →](API-OPTIMIZATION.md)
