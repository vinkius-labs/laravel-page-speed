<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;

/**
 * Smart ETag Middleware for APIs
 * 
 * Generates ETags for API responses and returns 304 Not Modified when appropriate.
 * This saves bandwidth without modifying any data - client gets same response,
 * just more efficiently.
 * 
 * Features:
 * - Automatic ETag generation based on response content
 * - 304 Not Modified support
 * - Configurable ETag algorithm (MD5 or SHA1)
 * - Works with any JSON/XML API response
 * - Zero data modification
 */
class ApiETag extends PageSpeed
{
    /**
     * Apply - not used in this middleware
     * (Required by PageSpeed abstract class)
     *
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        return $buffer;
    }

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

        if (! $this->shouldAddETag($request, $response)) {
            return $response;
        }

        return $this->processETag($request, $response);
    }

    /**
     * Process ETag logic.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return \Illuminate\Http\Response
     */
    protected function processETag($request, $response)
    {
        $content = $response->getContent();

        // Generate ETag from content
        $etag = $this->generateETag($content);

        // Set ETag header
        $response->headers->set('ETag', $etag);

        // Check if client sent If-None-Match header
        $clientETag = $request->header('If-None-Match');

        if ($clientETag === $etag) {
            // Content hasn't changed - return 304 Not Modified
            $response->setStatusCode(304);
            $response->setContent('');

            // Remove content-related headers
            $response->headers->remove('Content-Length');
            $response->headers->remove('Content-Type');
        } else {
            // Add Cache-Control header to enable caching
            // Always set it to ensure proper caching behavior
            $maxAge = config('laravel-page-speed.api.etag_max_age', 300); // 5 minutes default
            $response->headers->set('Cache-Control', "private, max-age={$maxAge}, must-revalidate");
        }

        return $response;
    }

    /**
     * Generate ETag from content.
     *
     * @param string $content
     * @return string
     */
    protected function generateETag($content)
    {
        $algorithm = config('laravel-page-speed.api.etag_algorithm', 'md5');

        $hash = match ($algorithm) {
            'sha1' => sha1($content),
            'sha256' => hash('sha256', $content),
            default => md5($content),
        };

        // Wrap in quotes as per HTTP spec
        return '"' . $hash . '"';
    }

    /**
     * Determine if ETag should be added.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldAddETag($request, $response)
    {
        // Check if middleware is enabled
        if (! $this->shouldProcessPageSpeed($request, $response)) {
            return false;
        }

        // Only add to successful GET requests
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Only add to successful responses
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        // Don't add if ETag already exists
        if ($response->headers->has('ETag')) {
            return false;
        }

        // Only add to API responses
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/xml')
            || str_contains($contentType, 'application/vnd.api+json');
    }
}
