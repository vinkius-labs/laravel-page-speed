<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class InlineCssTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new InlineCss();
    }

    public function test_apply(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString('<style>.page_speed_', $response->getContent());
        $this->assertStringContainsString('class="page_speed_', $response->getContent());
        $this->assertStringContainsString('class="btn page_speed_', $response->getContent());
        $this->assertStringContainsString('class="selected page_speed_', $response->getContent());

        $this->assertStringNotContainsString('style="background-image: url(\'img/test-bg.jpg\');"', $response->getContent());
        $this->assertStringNotContainsString('style="height:300px; padding:10px"', $response->getContent());
        $this->assertStringNotContainsString('style="display:block;border:1px solid red;"', $response->getContent());
        $this->assertStringNotContainsString('style="border:1px solid red"', $response->getContent());
        $this->assertStringNotContainsString('style="height:300px; padding:10px"', $response->getContent());
        $this->assertStringNotContainsString('style="cursor: default"', $response->getContent());
        $this->assertStringNotContainsString('style="border:3px solid blue;"', $response->getContent());
        $this->assertStringNotContainsString('style="border:1px solid red"', $response->getContent());
    }
}
