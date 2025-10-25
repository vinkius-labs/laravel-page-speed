<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use VinkiusLabs\LaravelPageSpeed\Entities\HtmlSpecs;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Log;

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
     * Performance: Added optional metrics tracking for debugging
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
        $originalSize = strlen($html);

        // Track performance metrics if in debug mode
        $startTime = config('app.debug') ? microtime(true) : null;

        $newContent = $this->apply($html);

        // Log performance metrics in debug mode
        if ($startTime !== null) {
            $this->logPerformanceMetrics(
                $startTime,
                $originalSize,
                strlen($newContent)
            );
        }

        return $response->setContent($newContent);
    }

    /**
     * Log performance metrics for debugging
     *
     * @param float $startTime Start time in microseconds
     * @param int $originalSize Original buffer size in bytes
     * @param int $finalSize Final buffer size in bytes
     * @return void
     */
    protected function logPerformanceMetrics($startTime, $originalSize, $finalSize)
    {
        $processTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $reduction = $originalSize > 0 ? round((1 - $finalSize / $originalSize) * 100, 2) : 0;
        $middlewareName = class_basename(static::class);

        // Only log if processing took significant time or achieved significant reduction
        if ($processTime > 1 || abs($reduction) > 1) {
            Log::debug("PageSpeed [{$middlewareName}]", [
                'time_ms' => round($processTime, 2),
                'original_kb' => round($originalSize / 1024, 2),
                'final_kb' => round($finalSize / 1024, 2),
                'reduction' => "{$reduction}%",
                'bytes_saved' => $originalSize - $finalSize,
            ]);
        }
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
     * Performance: Optimized to use preg_replace_callback for single-pass processing
     * instead of multiple str_replace operations on the entire buffer
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
        // Early return if no tags to process
        if (empty($tags)) {
            return $buffer;
        }

        // Build pattern for matching the tags
        $voidTags = array_intersect($tags, HtmlSpecs::voidElements());
        $normalTags = array_diff($tags, $voidTags);

        $patterns = [];

        // Pattern for void tags
        if (!empty($voidTags)) {
            $voidPattern = '/\<\s*(' . implode('|', $voidTags) . ')[^>]*\>/i';
            $patterns[] = $voidPattern;
        }

        // Pattern for normal tags
        if (!empty($normalTags)) {
            $normalPattern = '/\<\s*(' . implode('|', $normalTags) . ')[^>]*\>((.|\n)*?)\<\s*\/\s*\1\>/i';
            $patterns[] = $normalPattern;
        }

        // Performance: Use preg_replace_callback for single-pass processing
        // This is much faster than iterating and doing multiple str_replace on the entire buffer
        foreach ($patterns as $pattern) {
            $buffer = preg_replace_callback($pattern, function ($matches) use ($regex, $replace) {
                // Apply the regex replacement only within this tag
                return preg_replace($regex, $replace, $matches[0]);
            }, $buffer);
        }

        return $buffer;
    }
}
