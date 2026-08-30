<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Enums\Action;
use Illuminate\Http\Request;
use Tests\Support\Definitions\CategoryDefinition;
use Tests\Support\Models\Widget;

/**
 * Widget admin controller that is scoped to a parent, the way an admin
 * section listing only the active organisation's resources is: it hands its
 * policies an extra parameter describing that scope. Those parameters
 * describe *this* controller's resource, so they may never reach a policy
 * written for a related one.
 */
class ScopedWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        $this->setLayout('layouts.test');
        $this->setChildController(CategoryDefinition::class, CategoryFrontController::class);
    }

    protected function getAuthorizeParameters(Request $request, $action)
    {
        switch ($action) {
            case Action::INDEX:
            case Action::CREATE:
                return [ new Widget() ];
        }

        return null;
    }
}
