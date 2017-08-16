<?php

namespace CatLab\CharonFrontend;

use Illuminate\Support\ServiceProvider;

/**
 * Class CharonFrontendServiceProvider
 * @package CatLab\CharonFrontend
 */
class CharonFrontendServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'charonfrontend');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/charonfrontend')
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {

    }
}