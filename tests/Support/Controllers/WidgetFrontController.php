<?php

namespace Tests\Support\Controllers;

use CatLab\CharonFrontend\Controllers\FrontCrudController;
use Illuminate\Routing\Controller;

/**
 * Admin-panel-style front controller under test. Uses FrontCrudController
 * exactly the way an Eukles Admin\* controller would (see
 * FrontCrudControllerContract / README "Adding a new admin-manageable
 * resource").
 *
 * createFormView() is overridden on purpose: the real crud/form.blade.php
 * and crud/form-fields.blade.php views call the `Form::` and `Html::`
 * facades, which come from laravelcollective/html - a package that has no
 * Laravel 11/12 support (confirmed dead upstream, last compatible release
 * targets Laravel 10) and is therefore not, and should not become, a
 * dependency of this package. Rendering the real form blade would only be
 * possible by dragging in a facade provider that breaks the L12 install
 * target this whole test suite exists to protect. Overriding this single
 * documented extension point lets the test still drive the real
 * FrontCrudController::formView() logic (field filtering, relationship/
 * linkable discovery, action URL building) end to end and assert on its
 * output as a data structure, per the brief's explicit allowance for
 * "a response, a rendered view, a data structure" - just skipping the
 * blade render step that has a non-portable dependency.
 */
class WidgetFrontController extends Controller
{
    use FrontCrudController;

    public function __construct()
    {
        // Point at the minimal fixture layout registered in TestCase's
        // extra view path (tests/Support/resources/views/layouts/test.blade.php)
        // instead of the trait's 'layouts.app' default, which doesn't exist
        // in a testbench app.
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
        return new WidgetApiController();
    }

    protected function createFormView($action, $view, $properties)
    {
        $fieldNames = [];
        foreach ($properties['fields'] as $field) {
            $fieldNames[] = $field->getDisplayName();
        }

        return response()->json([
            'view' => $view,
            'fieldNames' => $fieldNames,
            'linkableCount' => count($properties['linkables']),
            'action' => $properties['action'],
            'verb' => $properties['verb'],
            'method' => $properties['method'],
        ]);
    }
}
