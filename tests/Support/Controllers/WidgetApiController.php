<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Laravel\Controllers\CrudController;
use Illuminate\Http\Request;
use Tests\Support\Definitions\WidgetDefinition;

/**
 * The Charon API controller that FrontCrudController delegates to
 * (see FrontCrudController::createApiController() / getApiController()).
 * Authorization is stubbed out to no-ops: these smoke tests are about
 * FrontCrudController wiring up table/form generation, not about policies.
 */
class WidgetApiController extends BaseResourceController
{
    use CrudController {
        CrudController::__construct as charonConstruct;
    }

    const RESOURCE_DEFINITION = WidgetDefinition::class;

    public function __construct()
    {
        $this->charonConstruct();
    }

    protected function authorizeIndex(Request $request, ...$args)
    {
        // No authorization for tests
    }

    protected function authorizeCreate(Request $request)
    {
        // No authorization for tests
    }

    protected function authorizeView(Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeEdit(Request $request, $entity)
    {
        // No authorization for tests
    }

    protected function authorizeDestroy(Request $request, $entity)
    {
        // No authorization for tests
    }
}
