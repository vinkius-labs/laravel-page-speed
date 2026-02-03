<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

use Closure;

/**
 * API Security Headers Middleware
 * 
 * Adds security headers to API responses to enhance security posture.
 * Does NOT modify the response data - only adds protective headers.
 * 
 * Headers added:
 * - X-Content-Type-Options: nosniff
 * - X-Frame-Options: DENY
 * - X-XSS-Protection: 1; mode=block
 * - Referrer-Policy: strict-origin-when-cross-origin
 * - Strict-Transport-Security: (HSTS for HTTPS)
 * - Content-Security-Policy: (for APIs)
 */
class ApiSecurityHeaders extends PageSpeed
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

        if (! $this->shouldAddSecurityHeaders($request, $response)) {
            return $response;
        }

        return $this->addSecurityHeaders($request, $response);
    }

    /**
     * Add security headers to the response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return \Illuminate\Http\Response
     */
    protected function addSecurityHeaders($request, $response)
    {
        $headers = $response->headers;

        // Prevent MIME type sniffing
        if (! $headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Prevent clickjacking for API endpoints that might return HTML errors
        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }

        // XSS Protection (legacy but still useful)
        if (! $headers->has('X-XSS-Protection')) {
            $headers->set('X-XSS-Protection', '1; mode=block');
        }

        // Referrer Policy
        if (! $headers->has('Referrer-Policy')) {
            $referrerPolicy = config('laravel-page-speed.api.referrer_policy', 'strict-origin-when-cross-origin');
            $headers->set('Referrer-Policy', $referrerPolicy);
        }

        // HSTS for HTTPS connections
        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $hstsMaxAge = config('laravel-page-speed.api.hsts_max_age', 31536000); // 1 year
            $hstsIncludeSubdomains = config('laravel-page-speed.api.hsts_include_subdomains', false);

            $hstsValue = "max-age={$hstsMaxAge}";
            if ($hstsIncludeSubdomains) {
                $hstsValue .= '; includeSubDomains';
            }

            $headers->set('Strict-Transport-Security', $hstsValue);
        }

        // Content Security Policy for APIs (restrictive)
        if (! $headers->has('Content-Security-Policy')) {
            $csp = config(
                'laravel-page-speed.api.content_security_policy',
                "default-src 'none'; frame-ancestors 'none'"
            );
            $headers->set('Content-Security-Policy', $csp);
        }

        // Permissions Policy (formerly Feature Policy)
        if (! $headers->has('Permissions-Policy')) {
            $permissionsPolicy = config(
                'laravel-page-speed.api.permissions_policy',
                'geolocation=(), microphone=(), camera=()'
            );
            $headers->set('Permissions-Policy', $permissionsPolicy);
        }

        return $response;
    }

    /**
     * Determine if security headers should be added.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Illuminate\Http\Response $response
     * @return bool
     */
    protected function shouldAddSecurityHeaders($request, $response)
    {
        // Add to all API responses
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/xml')
            || str_contains($contentType, 'application/vnd.api+json');
    }
}
