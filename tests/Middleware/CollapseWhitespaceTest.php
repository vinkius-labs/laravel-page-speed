<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;

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
            "<script> console.log('Laravel'); console.log('Page'); console.log('Speed!'); var url = \"http://example.com\"; var text = \"Some text\"; console.log('Important code'); </script>",
            $this->response->getContent()
        );
    }

    public function test_javascript_not_broken_by_comment_removal_and_whitespace_collapse(): void
    {
        // This test ensures that when comments are removed and whitespace is collapsed,
        // the JavaScript code remains functional and nothing is accidentally commented out
        $content = $this->response->getContent();

        // Ensure all expected JavaScript statements are present
        $this->assertStringContainsString("console.log('Laravel');", $content);
        $this->assertStringContainsString("console.log('Page');", $content);
        $this->assertStringContainsString("console.log('Speed!');", $content);
        $this->assertStringContainsString('var url = "http://example.com";', $content);
        $this->assertStringContainsString('var text = "Some text";', $content);
        $this->assertStringContainsString("console.log('Important code');", $content);

        // Ensure comments are removed
        $this->assertStringNotContainsString("// This comment should be removed", $content);
        $this->assertStringNotContainsString("// This comment should also be removed", $content);
        $this->assertStringNotContainsString("// Don't break this", $content);
        $this->assertStringNotContainsString("// Single Line Comment", $content);
    }
}
