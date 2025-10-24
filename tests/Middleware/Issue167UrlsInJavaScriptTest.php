<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments;

class Issue167UrlsInJavaScriptTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new RemoveComments();
    }

    /**
     * Test Issue #167: URLs in JavaScript should not have // removed
     * 
     * Problem reported:
     * https://www.test.com/ was changed to https:www.test.com
     * 
     * The RemoveComments middleware was incorrectly treating // in URLs
     * as the start of a comment and removing them.
     */
    public function test_preserves_https_urls_in_javascript(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = "https://www.test.com/";
console.log(url);
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        // URL should be completely preserved
        $this->assertStringContainsString(
            'https://www.test.com/',
            $result,
            'HTTPS URL should be preserved with //'
        );

        // Should NOT have broken URL
        $this->assertStringNotContainsString(
            'https:www.test.com',
            $result,
            'Bug #167: URL should not have // removed'
        );
    }

    /**
     * Test: HTTP URLs preservation
     */
    public function test_preserves_http_urls_in_javascript(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = "http://example.com/";
var api = "http://api.example.com/endpoint";
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('http://example.com/', $result);
        $this->assertStringContainsString('http://api.example.com/endpoint', $result);

        $this->assertStringNotContainsString(
            'http:example.com',
            $result,
            'HTTP URL should not have // removed'
        );
        $this->assertStringNotContainsString(
            'http:api.example.com',
            $result,
            'HTTP URL should not have // removed'
        );
    }

    /**
     * Test: Multiple URLs on same line
     */
    public function test_preserves_multiple_urls_on_same_line(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var urls = ["https://example.com/", "http://test.com/", "https://api.com/"];
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('https://example.com/', $result);
        $this->assertStringContainsString('http://test.com/', $result);
        $this->assertStringContainsString('https://api.com/', $result);
    }

    /**
     * Test: URL followed by actual comment
     */
    public function test_preserves_url_and_removes_actual_comment(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = "https://www.test.com/"; // this is a comment
var api = "http://api.test.com/endpoint"; // API endpoint
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        // URLs should be preserved
        $this->assertStringContainsString(
            'https://www.test.com/',
            $result,
            'HTTPS URL should be preserved'
        );
        $this->assertStringContainsString(
            'http://api.test.com/endpoint',
            $result,
            'HTTP URL should be preserved'
        );

        // Comments should be removed
        $this->assertStringNotContainsString(
            '// this is a comment',
            $result,
            'Actual comment should be removed'
        );
        $this->assertStringNotContainsString(
            '// API endpoint',
            $result,
            'Actual comment should be removed'
        );
    }

    /**
     * Test: URL in single quotes
     */
    public function test_preserves_urls_in_single_quotes(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = \'https://www.test.com/\';
var api = \'http://api.test.com/\';
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString("'https://www.test.com/'", $result);
        $this->assertStringContainsString("'http://api.test.com/'", $result);
    }

    /**
     * Test: URL concatenation
     */
    public function test_preserves_urls_in_concatenation(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var baseUrl = "https://example.com/";
var fullUrl = baseUrl + "api/users";
var endpoint = "https://" + domain + "/path";
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('https://example.com/', $result);
        $this->assertStringContainsString('https://', $result);
    }

    /**
     * Test: URLs in object properties
     */
    public function test_preserves_urls_in_objects(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var config = {
    apiUrl: "https://api.example.com/",
    websiteUrl: "http://www.example.com/",
    cdnUrl: "https://cdn.example.com/assets/"
};
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('https://api.example.com/', $result);
        $this->assertStringContainsString('http://www.example.com/', $result);
        $this->assertStringContainsString('https://cdn.example.com/assets/', $result);
    }

    /**
     * Test: Protocol-relative URLs
     */
    public function test_preserves_protocol_relative_urls(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = "//cdn.example.com/script.js";
var img = "//images.example.com/photo.jpg";
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        $this->assertStringContainsString(
            '//cdn.example.com/script.js',
            $result,
            'Protocol-relative URL should be preserved'
        );
        $this->assertStringContainsString(
            '//images.example.com/photo.jpg',
            $result,
            'Protocol-relative URL should be preserved'
        );
    }

    /**
     * Test: Mixed URLs and comments
     */
    public function test_complex_scenario_with_urls_and_comments(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
// API Configuration
var apiUrl = "https://api.example.com/v1/"; // Base API URL
var authUrl = "https://auth.example.com/"; // Auth endpoint

// Helper function
function getEndpoint(path) { // Concatenate URL
    return apiUrl + path; // Returns full URL
}

var endpoint = getEndpoint("users"); // Get users endpoint
</script>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        // URLs must be preserved
        $this->assertStringContainsString('https://api.example.com/v1/', $result);
        $this->assertStringContainsString('https://auth.example.com/', $result);

        // Comments must be removed
        $this->assertStringNotContainsString('// API Configuration', $result);
        $this->assertStringNotContainsString('// Base API URL', $result);
        $this->assertStringNotContainsString('// Auth endpoint', $result);
        $this->assertStringNotContainsString('// Helper function', $result);
        $this->assertStringNotContainsString('// Concatenate URL', $result);
        $this->assertStringNotContainsString('// Returns full URL', $result);
        $this->assertStringNotContainsString('// Get users endpoint', $result);

        // Code structure should remain
        $this->assertStringContainsString('var apiUrl =', $result);
        $this->assertStringContainsString('function getEndpoint(path)', $result);
    }

    /**
     * Test: Real-world scenario from Issue #167
     */
    public function test_issue_167_real_world_scenario(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <script>
        var websiteUrl = "https://www.test.com/";
        var apiEndpoint = "https://api.test.com/v1/data";
        
        fetch(apiEndpoint)
            .then(response => response.json())
            .then(data => console.log(data));
    </script>
</head>
<body>
    <p>Test</p>
</body>
</html>';

        $middleware = new RemoveComments();
        $result = $middleware->apply($html);

        // Critical: URLs must NOT be broken
        $this->assertStringContainsString(
            'https://www.test.com/',
            $result,
            'Issue #167: Website URL must be preserved'
        );
        $this->assertStringContainsString(
            'https://api.test.com/v1/data',
            $result,
            'Issue #167: API endpoint URL must be preserved'
        );

        // Verify URLs are not broken
        $this->assertStringNotContainsString(
            'https:www.test.com',
            $result,
            'Issue #167 BUG: URL should NOT have // stripped'
        );
        $this->assertStringNotContainsString(
            'https:api.test.com',
            $result,
            'Issue #167 BUG: URL should NOT have // stripped'
        );
    }
}
