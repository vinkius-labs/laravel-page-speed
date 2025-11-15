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

    /**
     * Test: Regex literal with flags and trailing comment should be preserved
     */
    public function test_regex_with_flags_and_trailing_comment_is_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var re = /http:\/\/\//gi; // trailing comment for regex
        var url = "http://example.com"; // url comment
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

        // Regex and URL should be preserved
        $this->assertStringContainsString('/http:\/\/\//gi', $content);
        $this->assertStringContainsString('http://example.com', $content);

        // Comments should be removed
        $this->assertStringNotContainsString('// trailing comment for regex', $content);
        $this->assertStringNotContainsString('// url comment', $content);
    }

    /**
     * Test: Backtick template literals that contain // should be preserved
     */
    public function test_template_literals_with_slashes_preserved_and_trailing_comments_removed(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var tpl = `this has // inside and should be kept`;
        var tpl2 = `value: ${getValue()}`; // trailing template comment
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

        $this->assertStringContainsString('this has // inside and should be kept', $content);
        $this->assertStringContainsString('value: ${getValue()}', $content);
        $this->assertStringNotContainsString('// trailing template comment', $content);
    }

    /**
     * Test: Regex pattern that targets double slashes should be preserved
     */
    public function test_regex_targeting_double_slashes_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var re = /\/\//; // should still preserve the regex that matches //
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

        $this->assertStringContainsString('/\/\//', $content);
        $this->assertStringNotContainsString('// should still preserve the regex that matches //', $content);
    }

    /**
     * Test: Inline URL and regex on same line keep their content and remove trailing comment
     */
    public function test_regex_and_url_and_trailing_comments_on_same_line(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var re = /test\/pattern/; var url = "https://example.com/"; // final comment
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

        $this->assertStringContainsString('/test\/pattern/', $content);
        $this->assertStringContainsString('https://example.com/', $content);
        $this->assertStringNotContainsString('// final comment', $content);
    }

    /**
     * Test: Comments after object property are removed while keeping URLs
     */
    public function test_object_property_with_url_and_trailing_comment_removed(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var cfg = { api: "http://api.example.com/" }; // API endpoint
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

        $this->assertStringContainsString('http://api.example.com/', $content);
        $this->assertStringNotContainsString('// API endpoint', $content);
    }

    /**
     * Test: regex with character classes and quantifiers preserved
     */
    public function test_regex_with_charsets_and_quantifiers_preserved_full_flow(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var re = /[A-Za-z0-9_\-]{1,5}\/path\/.+?/g; // comment
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

        $this->assertStringContainsString('/[A-Za-z0-9_\-]{1,5}\/path\/.+?/g', $content);
        $this->assertStringNotContainsString('// comment', $content);
    }

    /**
     * Test: multiline template literal with // inside should be preserved
     */
    public function test_multiline_backtick_literal_preserved_full_flow(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var tpl = `line1 // not a comment
line2 // not a comment
line3`; // trailing comment
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

        $this->assertStringContainsString('line1 // not a comment', $content);
        $this->assertStringContainsString('line2 // not a comment', $content);
        $this->assertStringNotContainsString('// trailing comment', $content);
    }

    /**
     * Test: minified single-line code with trailing comment
     */
    public function test_minified_single_line_code_comments_removed(): void
    {
        $html = '<!DOCTYPE html><html><body><script>var a=1;var b=2;var re=/[a-z]{1,3}\/test/g;var url="http://example.com"; //comment</script></body></html>';

        $request = Request::create('/test', 'GET');
        $response = new Response($html);

        $collapseWhitespace = new CollapseWhitespace();
        $final = $collapseWhitespace->handle($request, function () use ($response) {
            return $response;
        });

        $content = $final->getContent();

        $this->assertStringContainsString('var a=1;var b=2;var re=/[a-z]{1,3}\/test/g;var url="http://example.com";', $content);
        $this->assertStringNotContainsString('//comment', $content);
    }

    /**
     * Test: regex lookahead/lookbehind patterns preserved
     */
    public function test_regex_lookaround_patterns_preserved(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <script>
        var p1 = /(?<=abc)def/;
        var p2 = /foo(?=bar)/;
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

        $this->assertStringContainsString('/(?<=abc)def/', $content);
        $this->assertStringContainsString('/foo(?=bar)/', $content);
    }

    protected function getMiddleware()
    {
        // Not used in this test
    }
}
