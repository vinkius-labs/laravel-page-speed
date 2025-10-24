<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class DeferJavascriptTest extends TestCase
{
    public function test_defer_javascript(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString('Boilerplate/js/main.js" defer></script>', $response->getContent());
        $this->assertStringContainsString('Boilerplate/js/plugins.js" data-pagespeed-no-defer></script>', $response->getContent());

        $this->assertStringNotContainsString('analytics.js" async defer defer></script>', $response->getContent());
        $this->assertStringNotContainsString('<script defer>window.jQuery', $response->getContent());
    }

    protected function getMiddleware()
    {
        $this->middleware = new DeferJavascript();
    }
}
