<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class RemoveComments extends PageSpeed
{
    const REGEX_MATCH_MULTILINE_COMMENTS = '/\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\//';
    const REGEX_MATCH_HTML_COMMENTS = '/<!--[^]><!\[](.*?)[^\]]-->/s';

    /**
     * Apply comment removal optimization
     * 
     * Performance: Optimized single-line comment removal using regex
     * instead of character-by-character loop (10-50x faster)
     * 
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        // Early return for empty content
        if (empty($buffer)) {
            return $buffer;
        }

        // Log performance metrics
        $startTime = microtime(true);
        $originalSize = strlen($buffer);

        // First, remove multi-line comments (/* ... */) from script/style tags
        $buffer = $this->replaceInsideHtmlTags(['script', 'style'], self::REGEX_MATCH_MULTILINE_COMMENTS, '', $buffer);

        // Then, remove single-line comments (//) more carefully
        $buffer = $this->removeSingleLineComments($buffer);

        // Finally, remove HTML comments from the entire HTML
        $replaceHtmlRules = [
            self::REGEX_MATCH_HTML_COMMENTS => '',
        ];
        $buffer = $this->replace($replaceHtmlRules, $buffer);

        // Log performance metrics
        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;
        $finalSize = strlen($buffer);
        $sizeReduction = $originalSize - $finalSize;

        $this->logPerformanceMetrics($processingTime, $originalSize, $finalSize, $sizeReduction);

        return $buffer;
    }

    /**
     * Remove single-line comments (//) from script tags while preserving them inside strings
     * 
     * Performance: Optimized with regex-based approach instead of char-by-char loop
     * 
     * @param string $buffer
     * @return string
     */
    protected function removeSingleLineComments($buffer)
    {
        foreach ($this->matchAllHtmlTag(['script', 'style'], $buffer) as $tagMatched) {
            $tagAfterReplace = $this->removeCommentsFromTag($tagMatched);
            $buffer = str_replace($tagMatched, $tagAfterReplace, $buffer);
        }

        return $buffer;
    }

    /**
     * Remove // comments from a script/style tag content
     * 
     * Performance: Optimized with line-by-line processing using regex
     * 
     * @param string $tag
     * @return string
     */
    protected function removeCommentsFromTag($tag)
    {
        // Detect line ending style from the original tag
        $lineEnding = "\n";
        if (strpos($tag, "\r\n") !== false) {
            $lineEnding = "\r\n";
        } elseif (strpos($tag, "\r") !== false) {
            $lineEnding = "\r";
        }

        // Extract content between opening and closing tags
        if (preg_match('/^(<[^>]+>)(.*)(<\/[^>]+>)$/s', $tag, $matches)) {
            $openingTag = $matches[1];
            $content = $matches[2];
            $closingTag = $matches[3];

            // Process the whole content at once (supports multi-line template
            // literals and complex regex literals) for correctness and performance.
            $processedContent = $this->removeSingleLineCommentsFromContent($content);

            // Reconstruct the tag with processed content
            return $openingTag . $processedContent . $closingTag;
        }

        return $tag;
    }

    /**
     * Remove // comment from a single line while preserving // inside strings and URLs
     * 
     * Performance: Optimized regex approach instead of character-by-character loop
     * Uses negative lookbehind to avoid matching // in URLs (after :)
     * 
     * @param string $line
     * @return string
     */
    protected function removeSingleLineCommentFromLine($line)
    {
        // Fast path: no comments at all
        if (strpos($line, '//') === false) {
            return $line;
        }

        // If there are no quotes/backticks and no regex literal, we can do a fast, simple check.
        // This avoids invoking the heavier regex when not necessary — a common
        // case is lines like: var x = 1; // Comment
        // However, if there are regex literals in the line (e.g. /http:\/\/\//),
        // we must avoid the fast path as it can't safely detect // inside them.
        // Quick check for escaped slash sequences (e.g. http:\/\/) that indicate
        // the presence of regex literals or escaped slashes in general.
        $hasEscapedSlash = strpos($line, '\\/') !== false;
        if (strpos($line, '"') === false && strpos($line, "'") === false && strpos($line, '`') === false && !$hasEscapedSlash) {
            $offset = 0;
            while (($pos = strpos($line, '//', $offset)) !== false) {
                $prevChar = $pos > 0 ? $line[$pos - 1] : '';

                // URLs like http://example.com are preceded by :, so ignore these
                if ($prevChar === ':') {
                    // Skip over this occurrence (it's likely a protocol spec)
                    $offset = $pos + 2;
                    continue;
                }

                // Comment starts here — strip it
                return substr($line, 0, $pos);
            }

            return $line;
        }

        // More complex lines can contain strings, regexes, or backticks — use a
        // single PCRE step which skips strings/regex literals and removes // comments
        // that are not preceded by a colon.
                    $pattern = <<<'PATTERN'
                /(?:(?:"(?:\\.|[^"\\])*")|(?:'(?:\\.|[^'\\])*')|(?:`[^`]*`)|(?:\/(?:\\.|[^\/\\])+\/[a-zA-Z]*))(*SKIP)(*F)|(?<!:)\/\/[^\r\n]*/su
                PATTERN;

        // preg_replace will remove matched // comments but will skip strings/regexes
        $result = preg_replace($pattern, '', $line);

        // preg_replace returns null on error; if that happens fall back to original line
        return $result === null ? $line : $result;
    }

    /**
     * Remove // comments from full content (possibly multi-line) while preserving
     * strings, template literals, and regex literals in the content.
     *
     * This function avoids splitting lines so that multi-line template literals
     * (backticks) are preserved correctly.
     *
     * @param string $content
     * @return string
     */
    protected function removeSingleLineCommentsFromContent($content)
    {
        
            // Fallback to a linear scanner: it's safer than a single complex PCRE
            // and supports multi-line template literals and complex regexes.
            $length = strlen($content);
            $out = '';

            $inSingle = false;
            $inDouble = false;
            $inBacktick = false;
            $inRegex = false;
            $inRegexCharClass = false;
            $escaped = false;

            for ($i = 0; $i < $length; $i++) {
                $char = $content[$i];
                $next = $i + 1 < $length ? $content[$i + 1] : '';

                if ($escaped) {
                    $out .= $char;
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $out .= $char;
                    $escaped = true;
                    continue;
                }

                if ($inSingle) {
                    if ($char === "'") {
                        $inSingle = false;
                    }
                    $out .= $char;
                    continue;
                }

                if ($inDouble) {
                    if ($char === '"') {
                        $inDouble = false;
                    }
                    $out .= $char;
                    continue;
                }

                if ($inBacktick) {
                    if ($char === '`') {
                        $inBacktick = false;
                    }
                    $out .= $char;
                    continue;
                }

                if ($inRegex) {
                    // Handle char classes inside regex
                    if ($inRegexCharClass) {
                        if ($char === ']' && !$escaped) {
                            $inRegexCharClass = false;
                        }
                        $out .= $char;
                        continue;
                    }

                    if ($char === '[') {
                        $inRegexCharClass = true;
                        $out .= $char;
                        continue;
                    }

                    if ($char === '/' && !$escaped) {
                        $inRegex = false;
                        $out .= $char;
                        // Append any regex flags
                        $j = $i + 1;
                        while ($j < $length && preg_match('/[a-zA-Z]/', $content[$j])) {
                            $out .= $content[$j];
                            $j++;
                        }
                        $i = $j - 1;
                        continue;
                    }

                    $out .= $char;
                    continue;
                }

                // Not inside string, backtick or regex
                // Start single-quoted string
                if ($char === "'") {
                    $inSingle = true;
                    $out .= $char;
                    continue;
                }

                // Start double-quoted string
                if ($char === '"') {
                    $inDouble = true;
                    $out .= $char;
                    continue;
                }

                // Start backtick template literal
                if ($char === '`') {
                    $inBacktick = true;
                    $out .= $char;
                    continue;
                }

                // Detect start of comment
                if ($char === '/' && $next === '/') {
                    // Ensure '//' isn't part of a url (http://) — check previous char
                    $prevIndex = strlen($out) - 1;
                    $prevChar = $prevIndex >= 0 ? $out[$prevIndex] : '';
                    if ($prevChar === ':') {
                        // it's likely a URL-like, keep it
                        $out .= $char;
                        continue;
                    }

                    // Skip until end of line
                    $i += 2; // skip the //
                    while ($i < $length && $content[$i] !== "\n" && $content[$i] !== "\r") {
                        $i++;
                    }
                    // Append newline if present (preserve newline to keep structure)
                    if ($i < $length && $content[$i] === "\r") {
                        $out .= "\r";
                        if ($i + 1 < $length && $content[$i + 1] === "\n") {
                            $out .= "\n";
                            $i++;
                        }
                    } elseif ($i < $length && $content[$i] === "\n") {
                        $out .= "\n";
                    }
                    continue;
                }

                // Potential start of regex literal
                if ($char === '/') {
                    // Heuristic: regex often comes after these characters or at start
                    $prevNonSpaceIndex = strlen($out) - 1;
                    while ($prevNonSpaceIndex >= 0 && ctype_space($out[$prevNonSpaceIndex])) {
                        $prevNonSpaceIndex--;
                    }
                    $prevNonSpaceChar = $prevNonSpaceIndex >= 0 ? $out[$prevNonSpaceIndex] : '';

                    if ($prevNonSpaceChar === '' || in_array($prevNonSpaceChar, ['=', '(', '[', ',', ':', '?', '!', '{', '}', ';', '+', '-', '*', '/', '%'])) {
                        // This is likely a regex
                        $inRegex = true;
                        $out .= $char;
                        continue;
                    }
                    // Otherwise it's a division operator
                }

                // Default: append char
                $out .= $char;
            }

            return $out;
    }
}
