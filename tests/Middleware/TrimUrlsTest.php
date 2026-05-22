<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\TrimUrls;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class TrimUrlsTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new TrimUrls();
    }

    public function test_trim_urls(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        // Protocols should be stripped from src/href/action attributes
        $this->assertStringContainsString('src="//code.jquery.com/jquery-3.2.1.min.js"', $response->getContent());
        $this->assertStringContainsString('src="//github.com/vinkius-labs/', $response->getContent());

        // SVG xmlns with http:// should be preserved (not inside src/href/action)
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $response->getContent());
    }
}
