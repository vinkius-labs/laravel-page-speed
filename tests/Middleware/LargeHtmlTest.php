<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LargeHtmlTest extends TestCase
{
    /**
     * Test that comments are removed even in very large HTML documents
     */
    public function test_comments_removed_in_large_html(): void
    {
        // Generate a large HTML document
        $largeContent = $this->generateLargeHtml(5000); // 5000 lines

        $request = Request::create('/test', 'GET');
        $response = new Response($largeContent);

        $middleware = new RemoveComments();
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });

        $content = $result->getContent();

        // Ensure HTML comments are removed
        $this->assertStringNotContainsString('<!-- This is a test comment -->', $content);
        $this->assertStringNotContainsString('<!-- Another comment -->', $content);

        // Ensure JavaScript comments are removed
        $this->assertStringNotContainsString('// JavaScript comment', $content);
        $this->assertStringNotContainsString('/* Multi-line comment */', $content);

        // Ensure actual content is preserved
        $this->assertStringContainsString('console.log("test");', $content);
        $this->assertStringContainsString('<div class="content">', $content);
    }

    /**
     * Test with extremely large HTML (edge case)
     */
    public function test_comments_removed_in_extremely_large_html(): void
    {
        // Generate an extremely large HTML document (20,000 lines)
        $largeContent = $this->generateLargeHtml(20000);

        $request = Request::create('/test', 'GET');
        $response = new Response($largeContent);

        $middleware = new RemoveComments();
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });

        $content = $result->getContent();

        // Ensure HTML comments are removed
        $this->assertStringNotContainsString('<!-- This is a test comment -->', $content);

        // Ensure JavaScript comments are removed
        $this->assertStringNotContainsString('// JavaScript comment', $content);

        // Ensure the page is not blank
        $this->assertNotEmpty($content);
        $this->assertGreaterThan(10000, strlen($content));
    }

    /**
     * Generate large HTML content for testing
     */
    private function generateLargeHtml(int $lines): string
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Large HTML Test</title>
    <!-- This is a test comment -->
    <style>
        /* CSS comment */
        .test { color: red; }
    </style>
</head>
<body>
    <!-- Another comment -->
    <script>
        // JavaScript comment
        console.log("test");
        /* Multi-line comment */
        var url = "http://example.com";
    </script>
HTML;

        // Add many content blocks
        for ($i = 0; $i < $lines; $i++) {
            $html .= "\n    <div class=\"content\">\n";
            $html .= "        <!-- Comment $i -->\n";
            $html .= "        <p>Content line $i</p>\n";
            $html .= "        <script>\n";
            $html .= "            // Comment in script $i\n";
            $html .= "            console.log('Line $i');\n";
            $html .= "        </script>\n";
            $html .= "    </div>";
        }

        $html .= "\n</body>\n</html>";

        return $html;
    }

    protected function getMiddleware()
    {
        // Not used in this test
    }
}
