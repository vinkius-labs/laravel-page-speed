<?php

namespace RenatoMarinho\LaravelPageSpeed\Test\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RenatoMarinho\LaravelPageSpeed\Test\TestCase;
use RenatoMarinho\LaravelPageSpeed\Middleware\RemoveComments;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PageSpeedMiddlewareTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new RemoveComments();
    }

    /** @test */
    public function it_skips_binary_file_response()
    {
        $request = new Request();
        $binaryResponse = new BinaryFileResponse(__FILE__);
        
        $next = function () use ($binaryResponse) {
            return $binaryResponse;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertInstanceOf(BinaryFileResponse::class, $result);
    }

    /** @test */
    public function it_skips_streamed_response()
    {
        $request = new Request();
        $streamedResponse = new StreamedResponse(function () {
            echo 'streamed content';
        });
        
        $next = function () use ($streamedResponse) {
            return $streamedResponse;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        $this->assertInstanceOf(StreamedResponse::class, $result);
    }

    /** @test */
    public function it_skips_requests_matching_skip_patterns()
    {
        config(['laravel-page-speed.skip' => ['api/*', 'admin/*']]);
        
        $request = Request::create('/api/users', 'GET');
        $html = '<html><!-- comment --><body>Test</body></html>';
        $response = new Response($html);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        // Should not process, so comment should remain
        $this->assertStringContainsString('<!-- comment -->', $result->getContent());
    }

    /** @test */
    public function it_processes_requests_not_matching_skip_patterns()
    {
        config(['laravel-page-speed.skip' => ['api/*', 'admin/*']]);
        
        $request = Request::create('/home', 'GET');
        $html = '<html><!-- comment --><body>Test</body></html>';
        $response = new Response($html);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        // Should process, so comment should be removed
        $this->assertStringNotContainsString('<!-- comment -->', $result->getContent());
    }

    /** @test */
    public function it_respects_enable_config()
    {
        config(['laravel-page-speed.enable' => false]);
        
        $request = Request::create('/home', 'GET');
        $html = '<html><!-- comment --><body>Test</body></html>';
        $response = new Response($html);
        
        // Reset the static cache
        $reflection = new \ReflectionClass($this->middleware);
        $property = $reflection->getProperty('isEnabled');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        // Should not process when disabled
        $this->assertStringContainsString('<!-- comment -->', $result->getContent());
        
        // Re-enable for other tests
        config(['laravel-page-speed.enable' => true]);
        $property->setValue(null);
    }

    /** @test */
    public function it_matches_void_html_tags()
    {
        $request = Request::create('/home', 'GET');
        $html = '<html><body><img src="test.jpg" alt="test"><br><hr></body></html>';
        $response = new Response($html);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        // Just ensure it processes without errors
        $this->assertInstanceOf(Response::class, $result);
    }

    /** @test */
    public function it_matches_normal_html_tags()
    {
        $request = Request::create('/home', 'GET');
        $html = '<html><head><title>Test</title></head><body><div>Content</div></body></html>';
        $response = new Response($html);
        
        $next = function () use ($response) {
            return $response;
        };
        
        $result = $this->middleware->handle($request, $next);
        
        // Just ensure it processes without errors
        $this->assertInstanceOf(Response::class, $result);
    }
}
