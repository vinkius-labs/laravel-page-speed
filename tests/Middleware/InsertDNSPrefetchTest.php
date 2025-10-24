<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class InsertDNSPrefetchTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new InsertDNSPrefetch();
    }

    public function test_insert_dns_prefetch(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString('<link rel="dns-prefetch" href="//github.com">', $response->getContent());
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//browsehappy.com">', $response->getContent());
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//emblemsbf.com">', $response->getContent());
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//code.jquery.com">', $response->getContent());
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//www.google-analytics.com">', $response->getContent());
    }

    public function test_dns_prefetch_ignores_urls_inside_json_ld_script(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [{
            "@type": "Organization",
            "url": "https://legal.com",
            "logo": "https://legal.com/img/logo.svg"
        }, {
            "@type": "Article",
            "url": "https://legal.com/ia/leasehold-conveyancing",
            "headline": "Leasehold conveyancing",
            "author": {
                "@type": "Organization",
                "name": "Net Lawman",
                "url": "https://legal.com"
            },
            "publisher": {
                "url": "https://legal.com",
                "name": "Net Lawman"
            }
        }]
    }
    </script>
</head>
<body>
    <a href="https://example.com/page">Real Link</a>
</body>
</html>
HTML;

        $middleware = new InsertDNSPrefetch();
        $actual = $middleware->apply($html);

        // Should include DNS prefetch for actual HTML links
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//example.com">', $actual);

        // Should NOT include DNS prefetch for URLs inside JSON-LD script
        $this->assertStringNotContainsString('<link rel="dns-prefetch" href="//legal.com">', $actual);
        $this->assertStringNotContainsString('<link rel="dns-prefetch" href="//schema.org">', $actual);
    }

    public function test_dns_prefetch_ignores_urls_inside_javascript(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
    <script>
        var apiUrl = "https://api.example.com/data";
        fetch("https://backend.example.com/endpoint");
    </script>
</head>
<body>
    <img src="https://cdn.example.com/image.jpg">
</body>
</html>
HTML;

        $middleware = new InsertDNSPrefetch();
        $actual = $middleware->apply($html);

        // Should include DNS prefetch for actual HTML attributes
        $this->assertStringContainsString('<link rel="dns-prefetch" href="//cdn.example.com">', $actual);

        // Should NOT include DNS prefetch for URLs inside JavaScript
        $this->assertStringNotContainsString('<link rel="dns-prefetch" href="//api.example.com">', $actual);
        $this->assertStringNotContainsString('<link rel="dns-prefetch" href="//backend.example.com">', $actual);
    }
}
