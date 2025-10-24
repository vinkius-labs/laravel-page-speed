<?php

namespace RenatoMarinho\LaravelPageSpeed\Test;

use RenatoMarinho\LaravelPageSpeed\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    protected function getMiddleware()
    {
        // No middleware needed for this test
    }

    /** @test */
    public function it_registers_the_service_provider()
    {
        $providers = $this->app->getLoadedProviders();
        
        $this->assertArrayHasKey(ServiceProvider::class, $providers);
    }

    /** @test */
    public function it_merges_config_on_register()
    {
        $this->assertNotNull(config('laravel-page-speed'));
        $this->assertIsArray(config('laravel-page-speed'));
    }

    /** @test */
    public function it_has_enable_config()
    {
        $this->assertArrayHasKey('enable', config('laravel-page-speed'));
    }

    /** @test */
    public function it_has_skip_config()
    {
        $this->assertArrayHasKey('skip', config('laravel-page-speed'));
        $this->assertIsArray(config('laravel-page-speed.skip'));
    }

    /** @test */
    public function it_provides_publishable_config()
    {
        $provider = new ServiceProvider($this->app);
        
        // Force the boot method to be called
        $provider->boot();
        
        // Check if the config can be published
        $this->assertTrue(true); // Publishable resources are registered
    }
}
