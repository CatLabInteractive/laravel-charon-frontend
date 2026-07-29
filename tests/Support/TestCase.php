<?php

namespace Tests\Support;

use CatLab\Charon\Laravel\RESTResourceProvider;
use CatLab\CharonFrontend\CharonFrontendServiceProvider;
use CatLab\Laravel\Table\TableServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tests\Support\Controllers\WidgetApiController;
use Tests\Support\Controllers\WidgetFrontController;
use Tests\Support\Models\AdminUser;
use Tests\Support\Models\Widget;

/**
 * Base testbench TestCase, following the same shape as charon-laravel's
 * tests/Integration/IntegrationTestCase.php: sqlite in-memory DB via
 * RefreshDatabase, inline Schema::create() for the fixture table, routes
 * registered in defineRoutes().
 */
abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

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

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // FrontCrudController::create()/edit() write to the session
        // (frontcrud_index_redirect); keep it in memory for tests.
        $app['config']->set('session.driver', 'array');

        // Needed for the EncryptCookies/Session stack that StartSession
        // touches when building the response.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Extra view path providing the minimal fixture layout that
        // WidgetFrontController points $layout at (charonfrontend's crud
        // views @extend($layout), and 'layouts.app' doesn't exist here).
        $app['config']->set('view.paths', array_merge(
            $app['config']->get('view.paths', []),
            [__DIR__ . '/resources/views']
        ));
    }

    /**
     * No beforeApplicationDestroyed() teardown drop here on purpose: it
     * would run *after* RefreshDatabase's own teardown callback (registered
     * later in defineDatabaseMigrations() -> refreshDatabase() ordering,
     * but beforeApplicationDestroyed() unshifts, so callbacks run in LIFO
     * order) has already rolled back and disconnected the connection,
     * throwing on the disconnected PDO. It isn't needed anyway: SQLite DDL
     * is transactional, and afterRefreshingDatabase() runs inside the
     * per-test transaction RefreshDatabase begins, so the CREATE TABLE
     * below is undone by that same rollback at teardown.
     */
    protected function afterRefreshingDatabase()
    {
        Schema::dropIfExists('widgets');

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function defineRoutes($router)
    {
        // Charon JSON API routes, mirroring what an Eukles
        // app/Http/Api/V1/Controllers controller + routes.php entry would
        // wire up.
        Route::get('/widgets', [WidgetApiController::class, 'index']);
        Route::get('/widgets/{id}', [WidgetApiController::class, 'view']);

        // Front (admin) CRUD routes generated via FrontCrudController's own
        // ::routes() helper, same as an Eukles Admin\* controller would
        // register in routes/web.php. StartSession is attached explicitly
        // because testbench's default Kernel doesn't define a 'web'
        // middleware group; create()/edit() need a working session.
        Route::middleware([StartSession::class])->group(function () {
            WidgetFrontController::routes('/admin/widgets', WidgetFrontController::class);
        });
    }

    /**
     * FrontCrudController::isMethodAllowed() calls $request->user()->can(...)
     * directly via Laravel's Gate - independent of the Charon API
     * controller's own authorize*() hooks (those are stubbed out on
     * WidgetApiController). Bypass every ability check the way a superadmin
     * policy would, so these smoke tests exercise table/form building
     * rather than authorization wiring.
     */
    protected function actingAsAuthorizedAdmin(): AdminUser
    {
        Gate::before(function () {
            return true;
        });

        $user = new AdminUser();
        $this->actingAs($user);

        return $user;
    }

    protected function seedWidget(string $name = 'Widget One'): Widget
    {
        return Widget::create(['name' => $name]);
    }
}
