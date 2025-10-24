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
        // Updated to preserve single space between tags for Livewire compatibility (Issue #165)
        $compress = '<!DOCTYPE html><!--[if IE 8]> <html lang="en" class="ie8 no-js"> <![endif]--><!--[if IE 9]> <html lang="en" class="ie9 no-js"> <![endif]--><!--[if !IE]><!--><html lang="en"><!--<![endif]--> <head> <meta charset="utf-8"> <meta http-equiv="x-ua-compatible" content="ie=edge">';

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

    /**
     * Test #170: Preserve whitespace in <pre> tags
     */
    public function test_preserves_whitespace_in_pre_tags(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head><title>Test</title></head>
<body>
    <h1>Code Example</h1>
    <pre>
function example() {
    console.log("Hello World");
    return true;
}
    </pre>
    <p>Some text here</p>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // The <pre> content should preserve all whitespace
        $this->assertStringContainsString('<pre>
function example() {
    console.log("Hello World");
    return true;
}
    </pre>', $result);

        // But other whitespace should be collapsed
        $this->assertStringContainsString('<h1>Code Example</h1>', $result);
        $this->assertStringContainsString('<p>Some text here</p>', $result);
    }

    /**
     * Test #170: Preserve whitespace in <code> tags
     */
    public function test_preserves_whitespace_in_code_tags(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <p>Inline code: <code>var x    =    5;</code></p>
    <code>
    if (true) {
        doSomething();
    }
    </code>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Inline code with multiple spaces should be preserved
        $this->assertStringContainsString('<code>var x    =    5;</code>', $result);

        // Code block with indentation should be preserved
        $this->assertStringContainsString('<code>
    if (true) {
        doSomething();
    }
    </code>', $result);
    }

    /**
     * Test #170: Preserve whitespace in <textarea> tags
     */
    public function test_preserves_whitespace_in_textarea_tags(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <form>
        <textarea name="content">
Line 1
    Line 2 with indent
        Line 3 with more indent
</textarea>
    </form>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Textarea content should preserve all whitespace
        $this->assertStringContainsString('<textarea name="content">
Line 1
    Line 2 with indent
        Line 3 with more indent
</textarea>', $result);
    }

    /**
     * Test: Preserve whitespace in <pre><code> nested tags (common pattern)
     */
    public function test_preserves_whitespace_in_nested_pre_code_tags(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <pre><code class="language-php">
function calculateTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item->price;
    }
    return $total;
}
    </code></pre>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Both <pre> and <code> should preserve whitespace
        $this->assertStringContainsString('<pre><code class="language-php">
function calculateTotal($items) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item->price;
    }
    return $total;
}
    </code></pre>', $result);
    }

    /**
     * Test: Multiple code blocks on same page
     */
    public function test_preserves_multiple_code_blocks(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <h2>Example 1</h2>
    <pre>
    Code block 1
        with indents
    </pre>
    
    <h2>Example 2</h2>
    <pre>
    Code block 2
        different indents
    </pre>
    
    <p>Normal paragraph</p>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Both code blocks should preserve whitespace
        $this->assertStringContainsString('<pre>
    Code block 1
        with indents
    </pre>', $result);

        $this->assertStringContainsString('<pre>
    Code block 2
        different indents
    </pre>', $result);

        // Regular content should be collapsed
        $this->assertStringContainsString('<h2>Example 1</h2>', $result);
        $this->assertStringContainsString('<h2>Example 2</h2>', $result);
        $this->assertStringContainsString('<p>Normal paragraph</p>', $result);
    }

    /**
     * Test: Code block with attributes
     */
    public function test_preserves_code_blocks_with_attributes(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <pre class="language-javascript" id="code1" data-line-numbers>
function test() {
    return 42;
}
    </pre>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('<pre class="language-javascript" id="code1" data-line-numbers>
function test() {
    return 42;
}
    </pre>', $result);
    }

    /**
     * Test: Mixed content (code and regular HTML)
     */
    public function test_collapses_regular_html_but_preserves_code(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>    Test    Page    </title>
</head>
<body>
    <div class="container">
        <h1>    Blog    Post    </h1>
        
        <p>    This is a paragraph    with    extra    spaces.    </p>
        
        <pre>
def hello():
    print("Hello World")
    return True
        </pre>
        
        <p>    Another    paragraph    </p>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Regular HTML should have collapsed whitespace
        $this->assertStringContainsString('<title> Test Page </title>', $result);
        $this->assertStringContainsString('<h1> Blog Post </h1>', $result);
        $this->assertStringContainsString('<p> This is a paragraph with extra spaces. </p>', $result);
        $this->assertStringContainsString('<p> Another paragraph </p>', $result);

        // Code block should preserve whitespace
        $this->assertStringContainsString('<pre>
def hello():
    print("Hello World")
    return True
        </pre>', $result);
    }

    /**
     * Test: Empty code blocks
     */
    public function test_handles_empty_code_blocks(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <pre></pre>
    <code></code>
    <textarea></textarea>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('<pre></pre>', $result);
        $this->assertStringContainsString('<code></code>', $result);
        $this->assertStringContainsString('<textarea></textarea>', $result);
    }

    /**
     * Test: Code blocks with special characters
     */
    public function test_preserves_code_blocks_with_special_characters(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <pre>
&lt;script&gt;
    console.log("Hello");
&lt;/script&gt;

&lt;div class="example"&gt;
    Content
&lt;/div&gt;
    </pre>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('<pre>
&lt;script&gt;
    console.log("Hello");
&lt;/script&gt;

&lt;div class="example"&gt;
    Content
&lt;/div&gt;
    </pre>', $result);
    }

    /**
     * Test: Tabs and spaces in code blocks
     */
    public function test_preserves_tabs_and_spaces_in_code_blocks(): void
    {
        $html = "<!DOCTYPE html>
<html>
<body>
    <pre>
\tfunction example() {
\t\treturn true;
\t}
    </pre>
</body>
</html>";

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Tabs should be preserved in <pre>
        $this->assertStringContainsString("<pre>
\tfunction example() {
\t\treturn true;
\t}
    </pre>", $result);
    }

    /**
     * Test: Case insensitive tag matching
     */
    public function test_preserves_code_blocks_case_insensitive(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <PRE>
    Uppercase PRE
    </PRE>
    
    <Pre>
    Mixed case Pre
    </Pre>
    
    <CODE>
    Uppercase CODE
    </CODE>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('<PRE>
    Uppercase PRE
    </PRE>', $result);

        $this->assertStringContainsString('<Pre>
    Mixed case Pre
    </Pre>', $result);

        $this->assertStringContainsString('<CODE>
    Uppercase CODE
    </CODE>', $result);
    }

    /**
     * Test #170: Real-world blog code example scenario
     */
    public function test_real_world_blog_code_example(): void
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>    Laravel Tutorial - How to Create a Controller    </title>
</head>
<body>
    <div class="container">
        <article>
            <h1>    Laravel Tutorial    </h1>
            
            <p>    In this tutorial, we will learn how to create a controller in Laravel.    </p>
            
            <h2>    Step 1: Create the Controller    </h2>
            
            <p>    Run the following Artisan command:    </p>
            
            <pre><code class="language-bash">
php artisan make:controller UserController
            </code></pre>
            
            <h2>    Step 2: Add Methods    </h2>
            
            <p>    Open the controller and add the following code:    </p>
            
            <pre><code class="language-php">
&lt;?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        
        return view(\'users.index\', [
            \'users\' => $users
        ]);
    }
    
    public function show($id)
    {
        $user = User::findOrFail($id);
        
        return view(\'users.show\', compact(\'user\'));
    }
}
            </code></pre>
            
            <p>    That\'s it! Your controller is ready to use.    </p>
        </article>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Regular HTML should be collapsed
        $this->assertStringContainsString('<title> Laravel Tutorial - How to Create a Controller </title>', $result);
        $this->assertStringContainsString('<h1> Laravel Tutorial </h1>', $result);
        $this->assertStringContainsString('<p> In this tutorial, we will learn how to create a controller in Laravel. </p>', $result);

        // Code blocks should preserve all formatting
        $this->assertStringContainsString('<pre><code class="language-bash">
php artisan make:controller UserController
            </code></pre>', $result);

        $this->assertStringContainsString('<pre><code class="language-php">
&lt;?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        
        return view(\'users.index\', [
            \'users\' => $users
        ]);
    }
    
    public function show($id)
    {
        $user = User::findOrFail($id);
        
        return view(\'users.show\', compact(\'user\'));
    }
}
            </code></pre>', $result);
    }

    /**
     * Test: Performance - should handle many code blocks efficiently
     */
    public function test_handles_many_code_blocks_efficiently(): void
    {
        $html = '<!DOCTYPE html><html><body>';

        // Generate 50 code blocks
        for ($i = 1; $i <= 50; $i++) {
            $html .= "\n<h2>Example {$i}</h2>\n";
            $html .= "<pre><code>\nfunction example{$i}() {\n    return {$i};\n}\n</code></pre>\n";
        }

        $html .= '</body></html>';

        $middleware = new CollapseWhitespace();

        $startTime = microtime(true);
        $result = $middleware->apply($html);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should complete in reasonable time (under 100ms)
        $this->assertLessThan(100, $executionTime, "Processing 50 code blocks took {$executionTime}ms");

        // Verify all code blocks are preserved
        for ($i = 1; $i <= 50; $i++) {
            $this->assertStringContainsString("function example{$i}() {\n    return {$i};\n}", $result);
        }
    }
}
