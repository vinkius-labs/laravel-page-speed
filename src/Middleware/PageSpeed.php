<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use VinkiusLabs\LaravelPageSpeed\Entities\HtmlSpecs;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class PageSpeed
{
    protected static $isEnabled;

    /**
     * Apply rules.
     *
     * @param string $buffer
     * @return string
     */
    abstract public function apply($buffer);

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return \Illuminate\Http\Response $response
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldProcessPageSpeed($request, $response)) {
            return $response;
        }

        $html = $response->getContent();
        $newContent = $this->apply($html);

        return $response->setContent($newContent);
    }

    /**
     * Replace content response.
     *
     * @param  array $replace
     * @param  string $buffer
     * @return string
     */
    protected function replace(array $replace, $buffer)
    {
        $result = preg_replace(array_keys($replace), array_values($replace), $buffer);

        // Check for PCRE errors (e.g., backtrack limit, recursion limit exceeded)
        if ($result === null && preg_last_error() !== PREG_NO_ERROR) {
            // Log the error or handle it appropriately
            // For now, return the original buffer to prevent blank pages
            return $buffer;
        }

        return $result;
    }

    /**
     * Check Laravel Page Speed is enabled or not
     *
     * @return bool
     */
    protected function isEnable()
    {
        if (! is_null(static::$isEnabled)) {
            return static::$isEnabled;
        }

        static::$isEnabled = (bool) config('laravel-page-speed.enable', true);

        return static::$isEnabled;
    }

    /**
     * Should Process
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldProcessPageSpeed($request, $response)
    {
        if (! $this->isEnable()) {
            return false;
        }

        if ($response instanceof BinaryFileResponse) {
            return false;
        }

        if ($response instanceof StreamedResponse) {
            return false;
        }

        $patterns = config('laravel-page-speed.skip', []);

        foreach ($patterns as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match all occurrences of the html tags given
     *
     * @param array  $tags   Html tags to match in the given buffer
     * @param string $buffer Middleware response buffer
     *
     * @return array $matches Html tags found in the buffer
     */
    protected function matchAllHtmlTag(array $tags, string $buffer): array
    {
        $voidTags = array_intersect($tags, HtmlSpecs::voidElements());
        $normalTags = array_diff($tags, $voidTags);

        return array_merge(
            $this->matchTags($voidTags, '/\<\s*(%tags)[^>]*\>/', $buffer),
            $this->matchTags($normalTags, '/\<\s*(%tags)[^>]*\>((.|\n)*?)\<\s*\/\s*(%tags)\>/', $buffer)
        );
    }

    protected function matchTags(array $tags, string $pattern, string $buffer): array
    {
        if (empty($tags)) {
            return [];
        }

        $normalizedPattern = str_replace('%tags', implode('|', $tags), $pattern);

        preg_match_all($normalizedPattern, $buffer, $matches);

        return $matches[0];
    }

    /**
     * Replace occurrences of regex pattern inside of given HTML tags
     *
     * @param array  $tags    Html tags to match and run regex to replace occurrences
     * @param string $regex   Regex rule to match on the given HTML tags
     * @param string $replace Content to replace
     * @param string $buffer  Middleware response buffer
     *
     * @return string $buffer Middleware response buffer
     */
    protected function replaceInsideHtmlTags(array $tags, string $regex, string $replace, string $buffer): string
    {
        foreach ($this->matchAllHtmlTag($tags, $buffer) as $tagMatched) {
            preg_match_all($regex, $tagMatched, $contentsMatched);

            $tagAfterReplace = str_replace($contentsMatched[0], $replace, $tagMatched);
            $buffer = str_replace($tagMatched, $tagAfterReplace, $buffer);
        }

        return $buffer;
    }
}
