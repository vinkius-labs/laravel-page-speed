<?php

namespace RenatoMarinho\LaravelPageSpeed\Test\Middleware;

use RenatoMarinho\LaravelPageSpeed\Test\TestCase;
use RenatoMarinho\LaravelPageSpeed\Middleware\CollapseWhitespace;

class CollapseWhitespaceTest extends TestCase
{
    protected $response;

    public function setUp(): void
    {
        parent::setUp();

        $this->response = $this->middleware->handle($this->request, $this->getNext());
    }

    protected function getMiddleware()
    {
        $this->middleware = new CollapseWhitespace();
    }

    public function test_remove_comments_before_running_collapse_whitespace(): void
    {
        $this->assertStringNotContainsString(
            "/* before - inline comment*/console.log('Speed!');// after - inline comment",
            $this->response->getContent()
        );
    }

    public function test_collapse_whitespace(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $partial = explode('<title>', $this->response->getContent());
        $compress = '<!DOCTYPE html><!--[if IE 8]><html lang="en" class="ie8 no-js"><![endif]--><!--[if IE 9]><html lang="en" class="ie9 no-js"><![endif]--><!--[if !IE]><!--><html lang="en"><!--<![endif]--><head><meta charset="utf-8"><meta http-equiv="x-ua-compatible" content="ie=edge">';

        $this->assertSame($compress, trim($partial[0]));

        $this->assertStringContainsString(
            "<script> console.log('Laravel'); console.log('Page'); console.log('Speed!'); </script>",
            $this->response->getContent()
        );
    }
}
