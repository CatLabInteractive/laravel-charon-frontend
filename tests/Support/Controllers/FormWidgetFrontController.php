<?php

namespace Tests\Support\Controllers;

use CatLab\CharonFrontend\Widgets\TextareaWidget;
use Tests\Support\Definitions\CategoryDefinition;

/**
 * Widgets whose linkable options come from a scoped child controller, and a
 * per-field widget override.
 */
class FormWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->setChildController(CategoryDefinition::class, ScopedCategoryFrontController::class);

        $this->widgets()->forField('name', new TextareaWidget(false, [ 'rows' => 2 ]));
    }
}
