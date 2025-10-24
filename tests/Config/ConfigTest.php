<?php

namespace RenatoMarinho\LaravelPageSpeed\Test\Config;

use Illuminate\Http\Request;
use RenatoMarinho\LaravelPageSpeed\Middleware\TrimUrls;
use RenatoMarinho\LaravelPageSpeed\Test\TestCase;
use Mockery as m;

class ConfigTest extends TestCase
{
    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        m::close();
    }

    protected function getMiddleware()
    {
        $this->middleware = new TrimUrls();
    }

    public function test_disable_flag(): void
    {
        $middleware = $this->mockMiddlewareWithEnableFalse();
        $response = $middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString("https://", $response->getContent());
        $this->assertStringContainsString("http://", $response->getContent());
        $this->assertStringContainsString("https://code.jquery.com/jquery-3.2.1.min.js", $response->getContent());
    }


    public function test_enable_is_null(): void
    {
        $middleware = $this->mockMiddlewareWithEnableNull();
        $response = $middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString("//", $response->getContent());
        $this->assertStringContainsString("//", $response->getContent());
        $this->assertStringContainsString("//code.jquery.com/jquery-3.2.1.min.js", $response->getContent());
    }

    public function test_skip_route(): void
    {
        config(['laravel-page-speed.skip' => ['*/downloads/*', '*/downloads2/*']]);

        $request = Request::create('https://foo/bar/downloads/100', 'GET');

        $response = $this->middleware->handle($request, $this->getNext());

        $this->assertEquals($this->html, $response->getContent());
    }

    public function test_not_skip_route(): void
    {
        config(['laravel-page-speed.skip' => ['*/downloads/*', '*/downloads2/*']]);

        $request = Request::create('https://foo/bar/downloads3/100', 'GET');

        $response = $this->middleware->handle($request, $this->getNext());

        $this->assertNotEquals($this->html, $response->getContent());
    }

    public function test_skip_route_with_file_extension(): void
    {
        config(['laravel-page-speed.skip' => ['*.pdf', '*.csv']]);

        $request = Request::create('https://foo/bar/test.pdf', 'GET');

        $response = $this->middleware->handle($request, $this->getNext());

        $this->assertEquals($this->html, $response->getContent());
    }

    public function test_not_skip_route_with_file_extension(): void
    {
        config(['laravel-page-speed.skip' => ['*.pdf', '*.csv']]);

        $request = Request::create('https://foo/bar/test.php', 'GET');

        $response = $this->middleware->handle($request, $this->getNext());

        $this->assertNotEquals($this->html, $response->getContent());
    }

    public function test_wont_read_enable_config_more_than_once(): void
    {
        $pageSpeed = m::mock(TrimUrls::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        config(['laravel-page-speed.enable' => false]);

        $this->assertTrue($pageSpeed->isEnable());
    }

    protected function mockMiddlewareWithEnableNull()
    {
        $mock = m::mock(TrimUrls::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->shouldReceive('isEnable')
            ->once()
            ->andReturnNull();

        return $mock;
    }

    protected function mockMiddlewareWithEnableFalse()
    {
        $mock = m::mock(TrimUrls::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $mock->shouldReceive('isEnable')
            ->once()
            ->andReturnFalse();

        return $mock;
    }
}
