<?php

namespace Tests;

use CatLab\Charon\Interfaces\ResourceTransformer;
use CatLab\Charon\Laravel\RESTResourceProvider;
use CatLab\CharonFrontend\CharonFrontendServiceProvider;
use CatLab\Laravel\Table\TableServiceProvider;
use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase;

/**
 * Smoke test: does the app boot with CharonFrontendServiceProvider - and
 * the two providers it's built on top of, RESTResourceProvider (from
 * charon-laravel, see that package's composer.json
 * extra.laravel.providers) and TableServiceProvider (from
 * catlabinteractive/laravel-table) - registered, without throwing.
 */
class ServiceProviderTest extends TestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            RESTResourceProvider::class,
            TableServiceProvider::class,
            CharonFrontendServiceProvider::class,
        ];
    }

    /**
     * CharonFrontendServiceProvider::register() is empty and boot() only
     * calls loadViewsFrom()/publishes() - there's no container binding it
     * adds. Reaching this assertion at all means setUp() booted the
     * application with all three providers without an exception, which is
     * the smoke test itself.
     */
    public function testApplicationBootsWithProvidersRegistered()
    {
        $this->assertInstanceOf(\Illuminate\Foundation\Application::class, $this->app);
    }

    public function testCharonFrontendViewsAreRegistered()
    {
        $this->assertTrue(View::exists('charonfrontend::crud.index'));
        $this->assertTrue(View::exists('charonfrontend::crud.form'));
        $this->assertTrue(View::exists('charonfrontend::crud.form-fields'));
        $this->assertTrue(View::exists('charonfrontend::crud.view'));
        $this->assertTrue(View::exists('charonfrontend::crud.destroy'));
        $this->assertTrue(View::exists('charonfrontend::layouts.crud'));
    }

    public function testTableViewsAreRegistered()
    {
        $this->assertTrue(View::exists('table::table'));
        $this->assertTrue(View::exists('table::pagination'));
    }

    /**
     * RESTResourceProvider::register() binds a ResourceTransformer
     * singleton (see charon-laravel's src/RESTResourceProvider.php) - the
     * one concrete container binding among these three providers.
     */
    public function testRestResourceProviderRegistersResourceTransformer()
    {
        $this->assertTrue($this->app->bound(ResourceTransformer::class));
        $this->assertInstanceOf(
            ResourceTransformer::class,
            $this->app->make(ResourceTransformer::class)
        );
    }
}
