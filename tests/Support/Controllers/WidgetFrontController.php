<?php

namespace Tests\Support\Controllers;

use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Routing\Controller;
use Tests\Support\Definitions\CategoryDefinition;

/**
 * Admin-panel-style front controller under test. Uses FrontCrudController
 * exactly the way an Eukles Admin\* controller would (see
 * FrontCrudControllerContract / README "Adding a new admin-manageable
 * resource").
 *
 * As of Task 14, createFormView() is no longer overridden: crud/form.blade.php,
 * form-fields.blade.php and field.blade.php were rewritten to plain Blade/HTML
 * (see CatLab\CharonFrontend\Support\FormValue and task-14-report.md), so the
 * real view renders cleanly under Laravel 9-12 without laravelcollective/html.
 * This uses FrontCrudController's default createFormView() (`return view($view,
 * $properties);`), exercising the actual rendered HTML end to end.
 */
class WidgetFrontController extends Controller implements FrontCrudControllerContract
{
    use FrontCrudController;

    public function __construct()
    {
        // Point at the minimal fixture layout registered in TestCase's
        // extra view path (tests/Support/resources/views/layouts/test.blade.php)
        // instead of the trait's 'layouts.app' default, which doesn't exist
        // in a testbench app.
        $this->setLayout('layouts.test');

        // Lets tables link related categories to CategoryFrontController's
        // show page, the way Eukles' Admin\* controllers wire theirs.
        $this->setChildController(CategoryDefinition::class, CategoryFrontController::class);
    }

    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    public static function getApiRouteIdParameterName(): string
    {
        return 'id';
    }

    public function createApiController()
    {
        return new WidgetApiController();
    }
}
