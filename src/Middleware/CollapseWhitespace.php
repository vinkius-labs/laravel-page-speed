<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class CollapseWhitespace extends PageSpeed
{
    /**
     * Tags where whitespace should be preserved
     * 
     * Note: <script> and <style> are NOT included because:
     * - JavaScript and CSS minification is handled by their specific optimizers
     * - Collapsing whitespace in JS/CSS is generally safe and desired
     * - This middleware focuses on preserving user-visible formatted content
     */
    protected const PRESERVE_TAGS = [
        'pre',
        'code',
        'textarea',
    ];

    /**
     * Apply whitespace collapse to buffer while preserving content in specific tags
     */
    public function apply($buffer)
    {
        // First remove comments
        $buffer = $this->removeComments($buffer);

        // Extract and preserve content from whitespace-sensitive tags
        $preserved = [];
        $buffer = $this->extractPreservedContent($buffer, $preserved);

        // Apply whitespace collapse to the remaining content
        $replace = [
            "/\n([\S])/" => '$1',
            "/\r/" => '',
            "/\n/" => '',
            "/\t/" => '',
            "/ +/" => ' ',
            // Keep one space between tags for Livewire/Alpine.js compatibility (Issue #165)
            // This prevents breaking wire:* directives and x-* attributes
            "/> +</" => '> <',
        ];

        $buffer = $this->replace($replace, $buffer);

        // Restore preserved content
        $buffer = $this->restorePreservedContent($buffer, $preserved);

        return $buffer;
    }

    /**
     * Extract content from tags that should preserve whitespace
     */
    protected function extractPreservedContent(string $buffer, array &$preserved): string
    {
        $index = 0;

        foreach (self::PRESERVE_TAGS as $tag) {
            // Match opening and closing tags with all content in between
            // This regex handles:
            // - Tags with or without attributes
            // - Self-closing tags (though not common for these tags)
            // - Nested content
            // - Case-insensitive matching
            $pattern = '/<(' . $tag . ')(\s[^>]*)?>(.*?)<\/\1>/is';

            $buffer = preg_replace_callback($pattern, function ($matches) use (&$preserved, &$index) {
                $placeholder = "___PRESERVED_CONTENT_{$index}___";
                $preserved[$placeholder] = $matches[0]; // Store the entire tag with content
                $index++;
                return $placeholder;
            }, $buffer);
        }

        return $buffer;
    }

    /**
     * Restore preserved content back into the buffer
     */
    protected function restorePreservedContent(string $buffer, array $preserved): string
    {
        foreach ($preserved as $placeholder => $content) {
            $buffer = str_replace($placeholder, $content, $buffer);
        }

        return $buffer;
    }

    /**
     * Remove comments before collapsing whitespace
     */
    protected function removeComments($buffer)
    {
        return (new RemoveComments)->apply($buffer);
    }
}
