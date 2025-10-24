<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class RemoveComments extends PageSpeed
{
    const REGEX_MATCH_MULTILINE_COMMENTS = '/\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\//';
    const REGEX_MATCH_HTML_COMMENTS = '/<!--[^]><!\[](.*?)[^\]]-->/s';

    public function apply($buffer)
    {
        // First, remove multi-line comments (/* ... */)
        $buffer = $this->replaceInsideHtmlTags(['script', 'style'], self::REGEX_MATCH_MULTILINE_COMMENTS, '', $buffer);

        // Then, remove single-line comments (//) more carefully
        $buffer = $this->removeSingleLineComments($buffer);

        $replaceHtmlRules = [
            self::REGEX_MATCH_HTML_COMMENTS => '',
        ];

        return $this->replace($replaceHtmlRules, $buffer);
    }

    /**
     * Remove single-line comments (//) from script tags while preserving them inside strings
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
     * @param string $tag
     * @return string
     */
    protected function removeCommentsFromTag($tag)
    {
        // Detect line ending style
        $lineEnding = "\n";
        if (strpos($tag, "\r\n") !== false) {
            $lineEnding = "\r\n";
        } elseif (strpos($tag, "\r") !== false) {
            $lineEnding = "\r";
        }

        // Split by lines to process each line
        $lines = preg_split('/\r\n|\r|\n/', $tag);
        $processedLines = [];

        foreach ($lines as $line) {
            $processedLines[] = $this->removeSingleLineCommentFromLine($line);
        }

        return implode($lineEnding, $processedLines);
    }

    /**
     * Remove // comment from a single line while preserving // inside strings
     * 
     * @param string $line
     * @return string
     */
    protected function removeSingleLineCommentFromLine($line)
    {
        $result = '';
        $length = strlen($line);
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inRegex = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            $nextChar = $i + 1 < $length ? $line[$i + 1] : '';
            $prevChar = $i > 0 ? $line[$i - 1] : '';

            // Handle escape sequences
            if ($escaped) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\' && ($inSingleQuote || $inDoubleQuote || $inRegex)) {
                $result .= $char;
                $escaped = true;
                continue;
            }

            // Toggle quote states
            if ($char === '"' && !$inSingleQuote && !$inRegex) {
                $inDoubleQuote = !$inDoubleQuote;
                $result .= $char;
                continue;
            }

            if ($char === "'" && !$inDoubleQuote && !$inRegex) {
                $inSingleQuote = !$inSingleQuote;
                $result .= $char;
                continue;
            }

            // Handle regex literals (basic detection)
            if ($char === '/' && !$inSingleQuote && !$inDoubleQuote) {
                // Check if this might be a regex literal
                // Simple heuristic: regex usually comes after =, (, [, ,, return, or at start
                if ($prevChar === '=' || $prevChar === '(' || $prevChar === '[' || $prevChar === ',' || $prevChar === ' ') {
                    // Look ahead to see if this looks like a regex (not a comment)
                    if ($nextChar !== '/' && $nextChar !== '*') {
                        $inRegex = true;
                        $result .= $char;
                        continue;
                    }
                }

                // End of regex literal
                if ($inRegex) {
                    $inRegex = false;
                    $result .= $char;
                    continue;
                }
            }

            // Check for // comment outside of strings
            if (!$inSingleQuote && !$inDoubleQuote && !$inRegex && $char === '/' && $nextChar === '/') {
                // Check if this is not part of a URL (preceded by :)
                if ($prevChar !== ':') {
                    // Found a comment, remove everything from here to end of line
                    break;
                }
            }

            $result .= $char;
        }

        return $result;
    }
}
