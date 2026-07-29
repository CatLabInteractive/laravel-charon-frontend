<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Laravel\Controllers\ResourceController;
use Illuminate\Routing\Controller;

/**
 * Same two-layer split charon-laravel's own tests use (see
 * tests/Integration/Controllers/BaseResourceController.php in
 * charon-laravel): CrudController::__construct() calls
 * parent::__construct(static::RESOURCE_DEFINITION), so the "parent"
 * constructor that wires up the resource definition has to live one level
 * up from the class that `use`s CrudController.
 */
class BaseResourceController extends Controller
{
    use ResourceController;

    public function __construct($resourceDefinition = null)
    {
        if ($resourceDefinition) {
            $this->setResourceDefinition($resourceDefinition);
        }
    }
}
