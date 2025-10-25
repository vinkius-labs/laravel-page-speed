<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;

class MinifyJson extends PageSpeed
{
    /**
     * Apply JSON minification rules.
     *
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        // Try to decode JSON
        $data = json_decode($buffer, true);

        // If it's not valid JSON, return original buffer
        if (json_last_error() !== JSON_NO_ERROR) {
            return $buffer;
        }

        // Re-encode without pretty print (minified)
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

        if (! $this->shouldProcessJson($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        $minified = $this->apply($content);

        return $response->setContent($minified);
    }

    /**
     * Determine if the response should be processed.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldProcessJson($request, $response)
    {
        // Check if middleware is enabled
        if (! $this->shouldProcessPageSpeed($request, $response)) {
            return false;
        }

        // Check if it's a JSON response
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/vnd.api+json');
    }
}
