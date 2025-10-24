<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveQuotes;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class RemoveQuotesTest extends TestCase
{
    protected $response;

    public function setUp(): void
    {
        parent::setUp();

        $this->response = $this->middleware->handle($this->request, $this->getNext());
    }

    protected function getMiddleware()
    {
        $this->middleware = new RemoveQuotes();
    }

    public function test_remove_quotes(): void
    {
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="icon.png">', $this->response->getContent());
        $this->assertStringContainsString('<meta charset=utf-8>', $this->response->getContent());
        $this->assertStringContainsString('<meta name=viewport content="width=device-width, initial-scale=1">', $this->response->getContent());
        $this->assertStringContainsString('<img src=http://emblemsbf.com/img/18346.jpg width=250 style="height:300px; padding:10px" >', $this->response->getContent());
        $this->assertStringContainsString('<img src=/images/1000coin.png>', $this->response->getContent());
        $this->assertStringContainsString('<vue-component :src="\'src\'" :type="\'type\'" :width="200"></vue-component>', $this->response->getContent());
        $this->assertStringContainsString('<img src="tile whitespace.png" width=250 style="height:300px; padding:10px">', $this->response->getContent());
        $this->assertStringContainsString('<input type=text name="name with spaces" value="name with spaces" width=100%>', $this->response->getContent());
    }

    public function test_wont_remove_trailing_slashes_of_non_void_elements(): void
    {
        $this->assertStringContainsString('<path d="M 80 80 A 45 45, 0, 0, 0, 125 125 L 125 80 Z" fill="green"/>', $this->response->getContent());
        $this->assertStringContainsString('<path d="M 230 80 A 45 45, 0, 1, 0, 275 125 L 275 80 Z" fill="red"/>', $this->response->getContent());
    }
}
