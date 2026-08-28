<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Laravel\Controllers\CrudController;
use Illuminate\Http\Request;
use Tests\Support\Definitions\CategoryDefinition;

class CategoryApiController extends BaseResourceController
{
    use CrudController {
        CrudController::__construct as charonConstruct;
    }

    const RESOURCE_DEFINITION = CategoryDefinition::class;

    public function __construct()
    {
        $this->charonConstruct();
    }

    protected function authorizeIndex(Request $request, ...$args)
    {
    }

    protected function authorizeCreate(Request $request)
    {
    }

    protected function authorizeView(Request $request, $entity)
    {
    }

    protected function authorizeEdit(Request $request, $entity)
    {
    }

    protected function authorizeDestroy(Request $request, $entity)
    {
    }
}
