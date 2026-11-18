<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Models\RESTResource;
use Illuminate\Http\Request;
use Tests\Support\Definitions\WidgetDefinition;

/**
 * Categories as a hub, at /admin/hub-categories/{category}: a Widgets tab
 * for the owned widgets and an app tab that links elsewhere.
 */
class HubCategoryFrontController extends CategoryFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->setChildController(WidgetDefinition::class, CategoryWidgetFrontController::class);

        $this->addTab('history', 'History', function (Request $request, RESTResource $category) {
            return '/admin/hub-categories/' . $category->getIdentifiers()->getValues()[0]->getValue() . '/history';
        });
    }

    public static function getRouteIdParameterName(): string
    {
        return 'category';
    }

    public function createApiController()
    {
        return new CategoryWithWidgetsApiController();
    }

    /**
     * An app page that is one of the hub's tabs.
     */
    public function history(Request $request, $category)
    {
        $hub = $this->getHubChrome($request, $category, 'history');

        return view('history', [ 'layout' => 'layouts.test', 'hub' => $hub ]);
    }
}
