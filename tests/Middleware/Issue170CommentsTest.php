<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;

class Issue170CommentsTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new CollapseWhitespace();
    }

    /**
     * Test Issue #170: Comment after statement without semicolon breaks JavaScript
     * 
     * Original problem:
     * console.log('foo'); // undefined
     * var foo = 'bar';
     * 
     * After minification was incorrectly becoming:
     * console.log('foo'); // undefinedvar foo = 'bar';
     * 
     * The comment removal was not preserving line breaks, causing the next
     * statement to be commented out.
     */
    public function test_comment_after_statement_preserves_line_break(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
console.log(\'foo\'); // undefined
var foo = \'bar\';
console.log(foo); // bar
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Extract script content
        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // The bug would cause: console.log('foo'); // undefinedvar foo = 'bar';
        // We should NOT have the comment text anymore
        $this->assertStringNotContainsString(
            '// undefined',
            $scriptContent,
            'Comments should be removed'
        );
        $this->assertStringNotContainsString(
            '// bar',
            $scriptContent,
            'Comments should be removed'
        );

        // But we MUST have the actual code statements
        $this->assertStringContainsString(
            "console.log('foo');",
            $scriptContent,
            'First console.log should be present'
        );
        $this->assertStringContainsString(
            "var foo = 'bar';",
            $scriptContent,
            'Variable declaration should be present'
        );
        $this->assertStringContainsString(
            "console.log(foo);",
            $scriptContent,
            'Second console.log should be present'
        );

        // Critical: The statements should NOT be on the same line without separator
        // If bug exists, we would see: console.log('foo');var foo = 'bar';
        // We need either a space, newline, or semicolon between them
        $this->assertStringNotContainsString(
            "console.log('foo');var foo",
            $scriptContent,
            'Bug detected: Statements merged without separator after comment removal'
        );
    }

    /**
     * Test: Multiple statements with end-of-line comments
     */
    public function test_multiple_statements_with_eol_comments(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var x = 10; // initialize x
var y = 20; // initialize y
var z = x + y; // sum
console.log(z); // result
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Comments should be removed
        $this->assertStringNotContainsString('// initialize x', $scriptContent);
        $this->assertStringNotContainsString('// initialize y', $scriptContent);
        $this->assertStringNotContainsString('// sum', $scriptContent);
        $this->assertStringNotContainsString('// result', $scriptContent);

        // All statements should be present
        $this->assertStringContainsString('var x = 10;', $scriptContent);
        $this->assertStringContainsString('var y = 20;', $scriptContent);
        $this->assertStringContainsString('var z = x + y;', $scriptContent);
        $this->assertStringContainsString('console.log(z);', $scriptContent);

        // Bug check: Statements should not merge incorrectly
        $this->assertStringNotContainsString(
            '10;var y',
            $scriptContent,
            'Statements should not merge after comment removal'
        );
        $this->assertStringNotContainsString(
            '20;var z',
            $scriptContent,
            'Statements should not merge after comment removal'
        );
    }

    /**
     * Test: Statement without semicolon followed by comment
     */
    public function test_statement_without_semicolon_with_comment(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var name = "John" // no semicolon
console.log(name) // print it
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Comments removed
        $this->assertStringNotContainsString('// no semicolon', $scriptContent);
        $this->assertStringNotContainsString('// print it', $scriptContent);

        // Code present
        $this->assertStringContainsString('var name = "John"', $scriptContent);
        $this->assertStringContainsString('console.log(name)', $scriptContent);

        // Bug check: Even without semicolons, statements should not merge incorrectly
        $this->assertStringNotContainsString(
            '"John"console.log',
            $scriptContent,
            'Statements without semicolons should not merge after comment removal'
        );
    }

    /**
     * Test: Comments with URLs should preserve http:// and https://
     */
    public function test_comments_with_urls_preserve_protocols(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var url = "http://example.com"; // website URL
var secure = "https://example.com"; // secure URL
console.log(url); // http://example.com
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // URLs in code should be preserved
        $this->assertStringContainsString(
            'http://example.com',
            $scriptContent,
            'URL protocol should be preserved in code'
        );
        $this->assertStringContainsString(
            'https://example.com',
            $scriptContent,
            'Secure URL protocol should be preserved in code'
        );

        // Comments should be removed (including the URLs in comments)
        $this->assertStringNotContainsString('// website URL', $scriptContent);
        $this->assertStringNotContainsString('// secure URL', $scriptContent);

        // The actual variable declarations should remain intact
        $this->assertStringContainsString('var url = "http://example.com";', $scriptContent);
        $this->assertStringContainsString('var secure = "https://example.com";', $scriptContent);
    }

    /**
     * Test: Function with return statement and comment
     */
    public function test_function_with_return_and_comment(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
function getAnswer() {
    return 42; // the answer
}
var result = getAnswer(); // call it
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Comments removed
        $this->assertStringNotContainsString('// the answer', $scriptContent);
        $this->assertStringNotContainsString('// call it', $scriptContent);

        // Code structure preserved
        $this->assertStringContainsString('function getAnswer()', $scriptContent);
        $this->assertStringContainsString('return 42;', $scriptContent);
        $this->assertStringContainsString('var result = getAnswer();', $scriptContent);

        // Bug check
        $this->assertStringNotContainsString(
            '42;}var result',
            $scriptContent,
            'Return statement should not merge with next statement'
        );
    }

    /**
     * Test: If statement with comments
     */
    public function test_if_statement_with_comments(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
if (true) { // condition
    console.log("yes"); // log
} // end if
var done = true; // after if
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Comments removed
        $this->assertStringNotContainsString('// condition', $scriptContent);
        $this->assertStringNotContainsString('// log', $scriptContent);
        $this->assertStringNotContainsString('// end if', $scriptContent);
        $this->assertStringNotContainsString('// after if', $scriptContent);

        // Code structure preserved
        $this->assertStringContainsString('if (true)', $scriptContent);
        $this->assertStringContainsString('console.log("yes");', $scriptContent);
        $this->assertStringContainsString('var done = true;', $scriptContent);
    }

    /**
     * Test: Array declaration with comment
     */
    public function test_array_with_comment(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
<script>
var arr = [1, 2, 3]; // numbers
var first = arr[0]; // get first
var last = arr[2]; // get last
</script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Comments removed
        $this->assertStringNotContainsString('// numbers', $scriptContent);
        $this->assertStringNotContainsString('// get first', $scriptContent);
        $this->assertStringNotContainsString('// get last', $scriptContent);

        // Code preserved
        $this->assertStringContainsString('var arr = [1, 2, 3];', $scriptContent);
        $this->assertStringContainsString('var first = arr[0];', $scriptContent);
        $this->assertStringContainsString('var last = arr[2];', $scriptContent);

        // Bug check
        $this->assertStringNotContainsString(
            '[1, 2, 3];var first',
            $scriptContent,
            'Array declaration should not merge with next statement'
        );
    }

    /**
     * Test: Complex real-world scenario from Issue #170
     */
    public function test_real_world_issue_170_scenario(): void
    {
        $html = file_get_contents(__DIR__ . '/../Boilerplate/issue-170-comments.html');

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        preg_match('/<script>(.*?)<\/script>/s', $result, $matches);
        $scriptContent = $matches[1] ?? '';

        // Critical assertions for the exact bug reported
        $this->assertStringContainsString("console.log('foo');", $scriptContent);
        $this->assertStringContainsString("var foo = 'bar';", $scriptContent);
        $this->assertStringContainsString("console.log(foo);", $scriptContent);

        // The bug: statements should NOT be merged without proper separation
        $this->assertStringNotContainsString(
            "console.log('foo');var foo",
            $scriptContent,
            'BUG #170: Comment removal caused statements to merge incorrectly'
        );

        // All variable declarations should be intact
        $this->assertStringContainsString('var x = 10;', $scriptContent);
        $this->assertStringContainsString('var y = 20;', $scriptContent);
        $this->assertStringContainsString('var z = x + y;', $scriptContent);

        // URLs should be preserved
        $this->assertStringContainsString('http://example.com', $scriptContent);
        $this->assertStringContainsString('https://example.com', $scriptContent);

        // All comments should be removed
        $this->assertStringNotContainsString('// undefined', $scriptContent);
        $this->assertStringNotContainsString('// initialize x', $scriptContent);
        $this->assertStringNotContainsString('// website URL', $scriptContent);
    }
}
