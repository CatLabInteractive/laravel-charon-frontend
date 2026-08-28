<?php

namespace Tests\Support\Controllers;

use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Routing\Controller;

/**
 * Admin controller for the Category resource: the target of the links
 * WidgetFrontController's tables generate for the "category" relationship.
 */
class CategoryFrontController extends Controller implements FrontCrudControllerContract
{
    use FrontCrudController;

    public function __construct()
    {
        $this->setLayout('layouts.test');
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
        return new CategoryApiController();
    }
}
