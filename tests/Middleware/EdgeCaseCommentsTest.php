<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EdgeCaseCommentsTest extends TestCase
{
    /**
     * Test various edge cases that might not be handled correctly
     */
    public function test_comment_with_special_characters_before_it(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var a = 1;// Comment without space
        var b = 2; // Comment with space
        var c = 3;  // Comment with multiple spaces
        var d = 4;	// Comment with tab
        doSomething();
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);

        $collapseWhitespace = new CollapseWhitespace();
        $final = $collapseWhitespace->handle($request, function () use ($response) {
            return $response;
        });

        $content = $final->getContent();

        // All code should be present
        $this->assertStringContainsString('var a = 1;', $content);
        $this->assertStringContainsString('var b = 2;', $content);
        $this->assertStringContainsString('var c = 3;', $content);
        $this->assertStringContainsString('var d = 4;', $content);
        $this->assertStringContainsString('doSomething();', $content);

        // Comments should be removed
        $this->assertStringNotContainsString('// Comment without space', $content);
        $this->assertStringNotContainsString('// Comment with space', $content);
        $this->assertStringNotContainsString('// Comment with multiple spaces', $content);
        $this->assertStringNotContainsString('// Comment with tab', $content);
    }

    /**
     * Test comment after closing quote or parenthesis
     */
    public function test_comment_after_various_characters(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        func(); // After function call
        var x = getValue(); // After method
        var str = "text"; // After string
        var num = 42; // After number
        var arr = [1, 2]; // After array
        var obj = {a: 1}; // After object
        next();
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);

        $collapseWhitespace = new CollapseWhitespace();
        $final = $collapseWhitespace->handle($request, function () use ($response) {
            return $response;
        });

        $content = $final->getContent();

        // All code should be executable
        $this->assertStringContainsString('func();', $content);
        $this->assertStringContainsString('var x = getValue();', $content);
        $this->assertStringContainsString('var str = "text";', $content);
        $this->assertStringContainsString('var num = 42;', $content);
        $this->assertStringContainsString('var arr = [1, 2];', $content);
        $this->assertStringContainsString('var obj = {a: 1};', $content);
        $this->assertStringContainsString('next();', $content);

        // No comments should remain
        $this->assertStringNotContainsString('// After', $content);
    }

    /**
     * Test that // inside strings is not treated as comment
     */
    public function test_slashes_inside_strings_are_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var protocol = "http://";
        var url = "https://example.com/path";
        var comment = "This is not a // comment";
        var regex = /test\/pattern/;
        doSomething();
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);

        $collapseWhitespace = new CollapseWhitespace();
        $final = $collapseWhitespace->handle($request, function () use ($response) {
            return $response;
        });

        $content = $final->getContent();

        // Content inside strings should be preserved
        $this->assertStringContainsString('http://', $content);
        $this->assertStringContainsString('https://example.com/path', $content);
        $this->assertStringContainsString('This is not a // comment', $content);
        $this->assertStringContainsString('doSomething();', $content);
    }

    /**
     * Test regex pattern with slashes
     */
    public function test_regex_patterns_are_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var regex1 = /test\/pattern/;
        var regex2 = /http:\/\//;
        var result = text.match(/pattern/);
        doSomething();
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);

        $collapseWhitespace = new CollapseWhitespace();
        $final = $collapseWhitespace->handle($request, function () use ($response) {
            return $response;
        });

        $content = $final->getContent();

        // Regex patterns should be preserved
        $this->assertStringContainsString('/test\/pattern/', $content);
        $this->assertStringContainsString('/http:\/\//', $content);
        $this->assertStringContainsString('/pattern/', $content);
        $this->assertStringContainsString('doSomething();', $content);
    }

    protected function getMiddleware()
    {
        // Not used in this test
    }
}
