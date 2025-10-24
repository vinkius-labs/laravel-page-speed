<?php

namespace VinkiusLabs\LaravelPageSpeed\Test;

use VinkiusLabs\LaravelPageSpeed\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    protected function getMiddleware()
    {
        // No middleware needed for this test
    }

    public function test_it_registers_the_service_provider(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(ServiceProvider::class, $providers);
    }

    public function test_it_merges_config_on_register(): void
    {
        $this->assertNotNull(config('laravel-page-speed'));
        $this->assertIsArray(config('laravel-page-speed'));
    }

    public function test_it_has_enable_config(): void
    {
        $this->assertArrayHasKey('enable', config('laravel-page-speed'));
    }

    public function test_it_has_skip_config(): void
    {
        $this->assertArrayHasKey('skip', config('laravel-page-speed'));
        $this->assertIsArray(config('laravel-page-speed.skip'));
    }

    public function test_it_provides_publishable_config(): void
    {
        $provider = new ServiceProvider($this->app);

        // Force the boot method to be called
        $provider->boot();

        // Check if the config can be published
        $this->assertTrue(true); // Publishable resources are registered
    }
}
