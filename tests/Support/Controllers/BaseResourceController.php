<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Pagination\PaginationBuilder;
use CatLab\Charon\Processors\PaginationProcessor;
use Illuminate\Routing\Controller;

/**
 * Same two-layer split charon-laravel's own tests use (see
 * tests/Integration/Controllers/BaseResourceController.php in
 * charon-laravel): CrudController::__construct() calls
 * parent::__construct(static::RESOURCE_DEFINITION), so the "parent"
 * constructor that wires up the resource definition has to live one level
 * up from the class that `use`s CrudController.
 *
 * Like Eukles' api base controller it attaches charon's PaginationProcessor,
 * which is what turns the ?sort= parameter into an ORDER BY.
 */
class BaseResourceController extends Controller
{
    use ResourceController {
        getContext as traitGetContext;
    }

    public function __construct($resourceDefinition = null)
    {
        if ($resourceDefinition) {
            $this->setResourceDefinition($resourceDefinition);
        }
    }

    protected function getContext($action = Action::VIEW, $parameters = []): \CatLab\Charon\Interfaces\Context
    {
        $context = $this->traitGetContext($action, $parameters);
        $context->addProcessor(new PaginationProcessor(PaginationBuilder::class));

        return $context;
    }
}
