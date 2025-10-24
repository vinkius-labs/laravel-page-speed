<?php

namespace RenatoMarinho\LaravelPageSpeed;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-page-speed.php' => config_path('laravel-page-speed.php'),
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-page-speed.php', 'laravel-page-speed.php');
    }
}
