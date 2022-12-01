<?php

namespace JohnTout\LaravelImageSizes;

use Illuminate\Support\ServiceProvider;

class LaravelImageSizesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/image-sizes.php' => config_path('image-sizes.php'),
        ], 'laravel-image-sizes-config');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/image-sizes.php', 'image-sizes'
        );
    }
}
