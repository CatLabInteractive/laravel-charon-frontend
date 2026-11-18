<?php

namespace Tests\Support\Controllers;

use Illuminate\Http\Request;

/**
 * Widgets nested under a category, at /admin/categories/{category}/widgets,
 * the way catlab-events nests ticket categories under events.
 */
class CategoryWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->setParentController(CategoryFrontController::class, 'category');
    }

    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'category' => $request->route('category'),
        ];
    }
}
