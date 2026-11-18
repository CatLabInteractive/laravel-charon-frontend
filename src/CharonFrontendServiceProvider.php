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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'charonfrontend');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/charonfrontend')
        ]);

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/charonfrontend')
        ], 'charonfrontend-lang');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // One per request (and per job): the uploads of the save in progress.
        $this->app->scoped(\CatLab\CharonFrontend\Widgets\PendingUploads::class);
    }
}