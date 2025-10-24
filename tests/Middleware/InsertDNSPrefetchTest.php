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
}
