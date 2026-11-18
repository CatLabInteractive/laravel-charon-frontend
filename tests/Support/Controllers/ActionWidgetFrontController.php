<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Enums\Action;
use CatLab\Charon\Models\RESTResource;
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use Illuminate\Http\Request;

/**
 * Widgets with actions of the application, a list without the status, and
 * a delete that is worded as "retire".
 */
class ActionWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $id = function (RESTResource $widget) {
            return $widget->getIdentifiers()->getValues()[0]->getValue();
        };

        $this->addRowAction('Preview', function (Request $request, RESTResource $widget) use ($id) {
            return '/admin/action-widgets/' . $id($widget) . '/preview';
        });

        $this->addRowAction('Publish', function (Request $request, RESTResource $widget) use ($id) {
            return '/admin/action-widgets/' . $id($widget) . '/publish';
        }, [
            'method' => 'post',
            'style' => 'success',
            'confirm' => 'Publish this widget?',
            'condition' => function (Request $request, RESTResource $widget) {
                return $widget->toArray()['name'] !== 'Draft';
            },
        ]);

        $this->addRowAction('Move', function (Request $request, RESTResource $widget) use ($id) {
            return '/admin/action-widgets/' . $id($widget) . '/move';
        }, [
            'select' => [
                'name' => 'shelf',
                'options' => function (Request $request, RESTResource $widget) {
                    return $widget->toArray()['name'] === 'Draft' ? [] : [ 1 => 'Front', 2 => 'Back' ];
                },
                'selected' => function () {
                    return 2;
                },
            ],
        ]);

        $this->addRowAction('History', function (Request $request, RESTResource $widget) use ($id) {
            return '/admin/action-widgets/' . $id($widget) . '/history';
        }, [ 'menu' => true ]);

        $this->addPageAction('Duplicate', function (Request $request, RESTResource $widget) use ($id) {
            return '/admin/action-widgets/' . $id($widget) . '/duplicate';
        }, [ 'method' => 'post', 'style' => 'outline-primary', 'confirm' => 'Make a copy?' ]);

        $this->addPageAction('Open', function (Request $request, RESTResource $widget) use ($id) {
            return $widget->toArray()['name'] === 'Draft' ? null : '/widgets/' . $id($widget);
        });
    }

    public function createApiController()
    {
        return new ActionWidgetApiController();
    }

    protected function getHiddenIndexColumns(Request $request): array
    {
        return [ 'status' ];
    }

    protected function getHiddenIndexFilters(Request $request): array
    {
        return [ 'status' ];
    }

    protected function getDestroyConfirmText(Request $request, RESTResource $resource): string
    {
        return 'Retire ' . $resource->toArray()['name'] . '?';
    }

    protected function getDestroyConfirmLabel(Request $request, RESTResource $resource): string
    {
        return 'Retire';
    }

    protected function configureRowAction(string $action, ResourceAction $rowAction): ResourceAction
    {
        if ($action === Action::DESTROY) {
            $rowAction->setIcon(null)->showLabel()->setStyle('outline-danger');
        }

        return $rowAction;
    }
}
