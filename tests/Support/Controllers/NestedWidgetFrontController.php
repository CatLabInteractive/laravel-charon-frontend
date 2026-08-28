<?php

namespace Tests\Support\Controllers;

use Tests\Support\Definitions\CategoryDefinition;

/**
 * Widget admin controller whose Category child controller lives on a
 * nested route this page cannot fill in (see NestedCategoryFrontController).
 */
class NestedWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        $this->setLayout('layouts.test');
        $this->setChildController(CategoryDefinition::class, NestedCategoryFrontController::class);
    }
}
