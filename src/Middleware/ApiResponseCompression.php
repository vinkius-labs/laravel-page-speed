<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Response Compression Middleware
 * 
 * Compresses API responses using Brotli or Gzip to reduce bandwidth usage.
 * Does NOT modify the actual data - only compresses it for transport.
 * Client browsers automatically decompress the response.
 * 
 * Features:
 * - Auto-detects client compression support
 * - Only compresses responses above threshold size
 * - Preserves original data integrity
 * - Adds compression metrics headers
 */
class ApiResponseCompression extends PageSpeed
{
    /**
     * Minimum size in bytes to compress (default 1KB)
     */
    protected const MIN_COMPRESSION_SIZE = 1024;

    /**
     * Apply compression - not used in this middleware
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

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        return $this->compressResponse($request, $response);
    }

    /**
     * Compress the response with the best available algorithm.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return \Illuminate\Http\Response
     */
    protected function compressResponse($request, $response)
    {
        $content = $response->getContent();
        $originalSize = strlen($content);

        // Get client's accepted encodings
        $acceptEncoding = $request->header('Accept-Encoding', '');

        $compressed = null;
        $encoding = null;

        // Try Brotli first (best compression)
        if (function_exists('brotli_compress') && str_contains($acceptEncoding, 'br')) {
            $compressed = brotli_compress($content, 4); // Level 4 = balanced speed/compression
            $encoding = 'br';
        }
        // Fallback to Gzip
        elseif (function_exists('gzencode') && str_contains($acceptEncoding, 'gzip')) {
            $compressed = gzencode($content, 6); // Level 6 = balanced
            $encoding = 'gzip';
        }

        // Only use compressed version if it's actually smaller
        if ($compressed && strlen($compressed) < $originalSize) {
            $compressedSize = strlen($compressed);
            $savings = round((1 - $compressedSize / $originalSize) * 100, 2);

            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', $encoding);
            $response->headers->set('Content-Length', (string) $compressedSize);

            // Add performance metrics (can be disabled in config)
            if (config('laravel-page-speed.api.show_compression_metrics', false)) {
                $response->headers->set('X-Original-Size', (string) $originalSize);
                $response->headers->set('X-Compressed-Size', (string) $compressedSize);
                $response->headers->set('X-Compression-Savings', $savings . '%');
            }

            // Ensure proper cache handling with compression
            $response->headers->set('Vary', 'Accept-Encoding', false);
        }

        return $response;
    }

    /**
     * Determine if the response should be compressed.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldCompress($request, $response)
    {
        // Check if middleware is enabled
        if (! $this->shouldProcessPageSpeed($request, $response)) {
            return false;
        }

        // Don't compress if already compressed
        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        // Check if client supports compression
        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (! str_contains($acceptEncoding, 'gzip') && ! str_contains($acceptEncoding, 'br')) {
            return false;
        }

        // Only compress JSON/XML API responses
        $contentType = $response->headers->get('Content-Type', '');
        $isApiResponse = str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/xml')
            || str_contains($contentType, 'application/vnd.api+json')
            || str_contains($contentType, 'text/json');

        if (! $isApiResponse) {
            return false;
        }

        // Only compress if response is large enough
        $content = $response->getContent();
        $minSize = config('laravel-page-speed.api.min_compression_size', self::MIN_COMPRESSION_SIZE);

        if (strlen($content) < $minSize) {
            return false;
        }

        // Don't compress error responses if configured
        if (config('laravel-page-speed.api.skip_error_compression', false)) {
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                return false;
            }
        }

        return true;
    }
}
