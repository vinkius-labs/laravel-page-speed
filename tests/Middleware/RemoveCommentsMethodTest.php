<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class RemoveCommentsMethodTest extends TestCase
{
    protected function getMiddleware()
    {
        // Not used in this test class
    }

    /**
     * Provide a small wrapper to call the protected line-based method directly
     */
    protected function getRemoveCommentsWrapper()
    {
        return new class extends RemoveComments {
            public function callRemoveLine($line)
            {
                return $this->removeSingleLineCommentFromLine($line);
            }
            public function callRemoveContent($content)
            {
                return $this->removeSingleLineCommentsFromContent($content);
            }
        };
    }

    public function test_simple_comment_removed(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var x = 1; // a simple comment';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('var x = 1;', $result);
        $this->assertStringNotContainsString('// a simple comment', $result);
    }

    public function test_regex_with_flags_and_trailing_comment_removed_but_regex_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var re = /http:\/\/\//gi; // trailing comment';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('/http:\/\/\//gi', $result);
        $this->assertStringNotContainsString('// trailing comment', $result);
    }

    public function test_backtick_template_literal_preserved_and_trailing_comment_removed(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var tpl = `// inside `; // trailing';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('`// inside `', $result);
        $this->assertStringNotContainsString('// trailing', $result);
    }

    public function test_regex_matching_double_slashes_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var re = /\/\//; // matches //';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('/\/\//', $result);
        $this->assertStringNotContainsString('// matches //', $result);
    }

    public function test_multiple_comments_removed_only_preserve_code(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var a=1; //first //second';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('var a=1;', $result);
        $this->assertStringNotContainsString('//first', $result);
        $this->assertStringNotContainsString('//second', $result);
    }

    public function test_complex_nested_regex_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var re = /a(\/:\/\/\/)b/; // tricky';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('/a(\/:\/\/\/)b/', $result);
        $this->assertStringNotContainsString('// tricky', $result);
    }

    public function test_regex_with_charsets_and_quantifiers_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var re = /[A-Za-z0-9_\-]{1,5}\\/path\\/.+?/g; // comment here';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('/[A-Za-z0-9_\-]{1,5}\/path\/.+?/g', $result);
        $this->assertStringNotContainsString('// comment here', $result);
    }

    public function test_multiline_template_literal_preserved_by_content_method(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $content = "var tpl = `line1 // not a comment\nline2 // not a comment\nline3`; // trailing comment";
        $result = $wrapper->callRemoveContent($content);

        $this->assertStringContainsString('line1 // not a comment', $result);
        $this->assertStringNotContainsString('// trailing comment', $result);
    }

    public function test_minified_single_line_comments_removed_but_code_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var a=1;var b=2;var re=/[a-z]{1,3}\\/test/g;var url="http://example.com"; //comment';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('var a=1;var b=2;var re=/[a-z]{1,3}\/test/g;var url="http://example.com";', $result);
        $this->assertStringNotContainsString('//comment', $result);
    }

    public function test_regex_lookaround_preserved(): void
    {
        $wrapper = $this->getRemoveCommentsWrapper();
        $line = 'var p1 = /(?<=abc)def/; var p2 = /foo(?=bar)/; // comment';
        $result = $wrapper->callRemoveLine($line);

        $this->assertStringContainsString('/(?<=abc)def/', $result);
        $this->assertStringContainsString('/foo(?=bar)/', $result);
        $this->assertStringNotContainsString('// comment', $result);
    }
}
