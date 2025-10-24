<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JavaScriptCommentsBugTest extends TestCase
{
    /**
     * Test that JavaScript // comments don't break code when whitespace is collapsed
     * This reproduces the bug reported in the issue
     */
    public function test_javascript_comments_dont_break_code_after_collapse(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
    <script>
        var x = 1; // First comment
        var y = 2; // Second comment
        console.log(x + y);
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);
        
        $removeComments = new RemoveComments();
        $collapseWhitespace = new CollapseWhitespace();
        
        // Apply both middlewares
        $afterRemoveComments = $removeComments->handle($request, function() use ($response) {
            return $response;
        });
        
        $final = $collapseWhitespace->handle($request, function() use ($afterRemoveComments) {
            return $afterRemoveComments;
        });
        
        $content = $final->getContent();
        
        // The code should still be present and not commented out
        $this->assertStringContainsString('var x = 1;', $content);
        $this->assertStringContainsString('var y = 2;', $content);
        $this->assertStringContainsString('console.log(x + y);', $content);
        
        // Comments should be removed
        $this->assertStringNotContainsString('// First comment', $content);
        $this->assertStringNotContainsString('// Second comment', $content);
        
        // Make sure the second and third lines are not commented out
        // This would happen if the comment wasn't removed and newlines were collapsed
        $this->assertStringNotContainsString('// First comment var y = 2;', $content);
        $this->assertStringNotContainsString('// Second comment console.log', $content);
    }
    
    /**
     * Test edge case: comment after string with quote
     */
    public function test_comment_after_string_is_removed(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var url = "http://example.com"; // URL comment
        var name = 'John'; // Name comment
        doSomething();
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);
        
        $collapseWhitespace = new CollapseWhitespace();
        
        $final = $collapseWhitespace->handle($request, function() use ($response) {
            return $response;
        });
        
        $content = $final->getContent();
        
        // All code should be present
        $this->assertStringContainsString('var url = "http://example.com";', $content);
        $this->assertStringContainsString("var name = 'John';", $content);
        $this->assertStringContainsString('doSomething();', $content);
        
        // Comments should be removed
        $this->assertStringNotContainsString('// URL comment', $content);
        $this->assertStringNotContainsString('// Name comment', $content);
        
        // Ensure nothing is accidentally commented out
        $this->assertStringNotContainsString('// URL comment var name', $content);
        $this->assertStringNotContainsString('// Name comment doSomething', $content);
    }
    
    /**
     * Test that URLs with // are not affected
     */
    public function test_urls_with_slashes_are_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var url = "http://example.com";
        var url2 = 'https://test.com';
        fetch("http://api.example.com/data");
    </script>
</body>
</html>
HTML;

        $request = Request::create('/test', 'GET');
        $response = new Response($html);
        
        $collapseWhitespace = new CollapseWhitespace();
        
        $final = $collapseWhitespace->handle($request, function() use ($response) {
            return $response;
        });
        
        $content = $final->getContent();
        
        // URLs should be preserved
        $this->assertStringContainsString('http://example.com', $content);
        $this->assertStringContainsString('https://test.com', $content);
        $this->assertStringContainsString('http://api.example.com/data', $content);
    }

    protected function getMiddleware()
    {
        // Not used in this test
    }
}
