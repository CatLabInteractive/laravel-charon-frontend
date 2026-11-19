<?php

namespace CatLab\CharonFrontend\Controllers;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\IdentifierField;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Models\Properties\ResourceField;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\ChildrenValue;
use CatLab\Charon\Models\Values\ChildValue;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Exceptions\UnexpectedResponse;
use CatLab\CharonFrontend\Exceptions\UnresolvedMethodException;

use CatLab\CharonFrontend\Models\Table\CallbackAction;
use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\CharonFrontend\Widgets\FormField;
use CatLab\CharonFrontend\Widgets\PendingUploads;
use CatLab\CharonFrontend\Widgets\ProvidesOptions;
use CatLab\CharonFrontend\Widgets\Widget;
use CatLab\CharonFrontend\Widgets\WidgetRegistry;
use CatLab\Laravel\Table\Models\CollectionAction;
use CatLab\Laravel\Table\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Redirect;
use Route;
use Session;

/**
 * Class CrudController
 * @package CatLab\CharonFrontend\Controllers
 */
trait FrontCrudController
{
    /**
     * @param $path
     * @param $controller
     * @param string $modelId
     */
    public static function routes($path, $controller)
    {
        static::traitRoutes($path, $controller);
    }

    /**
     * Set routes
     * @param $path
     * @param $controller
     * @param string $modelId
     */
    public static function traitRoutes($path, $controller)
    {
        $modelId = static::getRouteIdParameterName();

        Route::get($path, $controller . '@index');
        Route::post($path, $controller . '@store');
        Route::get($path . '/create', $controller . '@create');
        Route::delete($path . '/{' . $modelId . '}', $controller . '@destroy');
        Route::get($path . '/{' . $modelId . '}', $controller . '@show');
        Route::put($path . '/{' . $modelId . '}', $controller . '@update');
        Route::get($path . '/{' . $modelId . '}/edit', $controller . '@edit');
        Route::get($path . '/{' . $modelId . '}/delete', $controller . '@confirmDelete');
    }

    /**
     * @return string
     */
    public abstract static function getRouteIdParameterName(): string;

    /**
     * @return string
     */
    public abstract static function getApiRouteIdParameterName(): string;

    /**
     * This method should return an instance of the corresponding api controller.
     * @return ResourceController
     */
    public abstract function createApiController();

    /**
     * @var mixed
     */
    private $apiController;

    /**
     * @var mixed
     */
    private $childControllerMap = [];

    /**
     * Child controllers instantiated so far, by class name.
     * @var FrontCrudControllerContract[]
     */
    private $childControllers = [];

    /**
     * @var string
     */
    private $layout = 'layouts.app';

    /**
     * @var WidgetRegistry|null
     */
    private $widgetRegistry;

    /**
     * Tabs registered with addTab().
     * @var array
     */
    private $appTabs = [];

    /**
     * Row actions registered with addRowAction().
     * @var array[]
     */
    private $appRowActions = [];

    /**
     * Page actions registered with addPageAction().
     * @var array[]
     */
    private $appPageActions = [];

    /**
     * @var array[] [ [ 'label' => string, 'fields' => string[], 'aside' => bool, 'collapsed' => bool, 'description' => ?string ] ]
     */
    private $fieldGroups = [];

    /**
     * The parent's fields on this controller's edit form, see editParentOnForm().
     * @var array|null [ 'label' => string, 'options' => array ]
     */
    private $parentFormGroup;

    /**
     * Extra submit buttons registered with addSaveButton().
     * @var array[] [ [ 'label' => string, 'value' => string ] ]
     */
    private $saveButtons = [];

    /**
     * The input the form fields live under: "fields", or "parent" while
     * this controller handles its fields on a child's form.
     * @var string
     */
    private $formRoot = 'fields';

    /**
     * The front controller this one is nested under, see setParentController().
     * @var array|null [ 'controller' => class name, 'parameter' => route parameter ]
     */
    private $parentController;

    /**
     * Map actions to api controller actions.
     * @var array
     */
    protected $routeMap = [
        Action::INDEX => 'index',
        Action::CREATE => 'store',
        Action::VIEW => 'view',
        Action::EDIT => 'edit',
        Action::DESTROY => 'destroy'
    ];

    /**
     * Map actions to front controller actions.
     * @var array
     */
    protected $frontControllerRouteMap = [
        Action::INDEX => 'index',
        Action::CREATE => 'create',
        Action::VIEW => 'show',
        Action::EDIT => 'edit',
        Action::DESTROY => 'confirmDelete',
    ];

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Response|View
     * @throws UnexpectedResponse
     */
    public function index(Request $request)
    {
        // The table's filter form submits every field, blank ones included
        // (as '' or, after ConvertEmptyStringsToNull, as null), and charon
        // would filter on that; drop them.
        foreach ($request->query->keys() as $key) {
            $value = $request->query->get($key);
            if ($value === '' || $value === null) {
                $request->query->remove($key);
            }
        }

        $response = $this->dispatchToApi(Action::INDEX, $request);

        if (!($response instanceof ResourceResponse)) {
            throw UnexpectedResponse::createNoResourceCollection($response);
        }

        $resourceCollection = $response->getResource();
        if (!($resourceCollection instanceof ResourceCollection)) {
            throw UnexpectedResponse::createNoResourceCollection($response);
        }

        $table = $this->getTableForResourceCollection(
            $request,
            $resourceCollection,
            $this->getResourceDefinition(),
            $response->getContext()
        );

        // The index request is forwarded to the api as-is, so charon's sort
        // and filter parameters in the url already apply; the table only
        // needs to expose them.
        $table->sortable()->filterable();

        $view = $this->getView('index');
        return view(
            $view,
            [
                'table' => $table,
                'layout' => $this->layout,
                'chrome' => $this->getPageChrome($request, Action::INDEX),
            ]
        );
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function create(Request $request)
    {
        // The form lists options (a parent's children, ...): only for those who may create.
        if (!$this->isMethodAllowed($request, Action::CREATE)) {
            abort(403);
        }

        return $this->formView(Action::CREATE, 'store');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function store(Request $request)
    {
        // Widgets may store uploads while reading the input; check first.
        if (!$this->isMethodAllowed($request, Action::CREATE)) {
            abort(403);
        }

        $response = $this->saveWithUploads($request, function () use ($request) {
            $newRequest = $this->transformFormInput($request, Action::CREATE);
            return $this->dispatchToApi(Action::CREATE, $newRequest);
        });

        if (!($response instanceof ResourceResponse)) {
            return $this->handleErrorResponse($response, 'store');
        }

        return $this->afterStore($request, $response->getResource());
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response|View
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function show(Request $request)
    {
        $response = $this->dispatchToApi(Action::VIEW, $request);
        $resource = $response->getResource();

        $view = $this->getView(Action::VIEW);

        $context = $response->getContext();

        if (! ($resource instanceof RESTResource)) {
            abort(404, 'Only Resources can be shown.');
        }

        $data = [
            'layout' => $this->layout,
            'resource' => $resource,
            'relationships' => [],
            'chrome' => $this->getPageChrome($request, Action::VIEW, $resource),
        ];

        // Single relationships and linked many-to-many ones (producers,
        // tags, ...) are rows of the details tab; owned child lists with a
        // child controller (ticket categories, dates, ...) get a tab each.
        $childTabs = [];
        foreach ($resource->getProperties()->getRelationships()->getValues() as $relationship) {

            $field = $relationship->getField();
            $childResourceDefinition = $field->getChildResourceDefinition();

            if ($relationship instanceof ChildValue) {
                // A single related resource is one row in the details table:
                // a label linking to its show page when a child controller is
                // known, a plain label otherwise. Only a cell is needed, so
                // the table is built bare: the actions a full table carries
                // belong to *this* controller's resource, not to the related
                // one, and asking for them here would point this controller's
                // routes and authorization at somebody else's definition.
                $table = $this->makeTable(
                    $request,
                    new ResourceCollection(),
                    $childResourceDefinition,
                    $context
                );

                $data['relationships'][] = [
                    'multiple' => false,
                    'property' => $relationship,
                    'title' => (new FormField($field))->getLabel(),
                    'cell' => $table->makeCell($relationship)
                ];
                continue;
            }

            if (!($relationship instanceof ChildrenValue)) {
                continue;
            }

            if ($field->canLinkExistingEntities(new Context(Action::EDIT, []))) {
                $links = [];
                foreach ($relationship->getChildren() as $child) {
                    $links[] = [
                        'label' => $this->getRelatedLabel($request, $child),
                        'url' => $this->getRelatedResourceUrl($request, $child),
                    ];
                }

                $data['relationships'][] = [
                    'multiple' => false,
                    'property' => $relationship,
                    'title' => (new FormField($field))->getLabel(),
                    'links' => $links,
                ];
                continue;
            }

            $childController = $this->getChildController($childResourceDefinition);
            if (!$childController) {
                continue;
            }

            $childTabs[$field->getDisplayName()] = [
                'label' => (new FormField($field))->getLabel(),
                'build' => function () use ($childController, $request, $relationship, $childResourceDefinition, $context) {
                    return $childController->getTableForResourceCollection(
                        $request,
                        $relationship->getChildren(),
                        $childResourceDefinition,
                        $context
                    );
                },
            ];
        }

        $tabs = $this->getShowTabs($request, $resource, $childTabs);
        $active = $this->getActiveTab($request, $tabs);
        foreach ($tabs as $key => $tab) {
            $tabs[$key]['active'] = $key === $active;
        }

        $data['tabs'] = count($tabs) > 1 ? $tabs : [];
        $data['activeTab'] = $active;
        $data['tabTable'] = isset($childTabs[$active]) ? call_user_func($childTabs[$active]['build']) : null;

        return view($view, $data);
    }

    /**
     * Show the edit form
     * @param Request $request
     * @return Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    public function edit(Request $request)
    {
        $response = $this->dispatchToApi(Action::VIEW, $request);
        return $this->formView(Action::EDIT, 'update', $response);
    }

    /**
     * Update an entity and redirect back to index page
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function update(Request $request, $id)
    {
        // Widgets may store uploads while reading the input; check first.
        $current = $this->dispatchToApi(Action::VIEW, $request);
        if (!($current instanceof ResourceResponse)) {
            return $current;
        }
        if (!$this->isMethodAllowed($request, Action::EDIT, $current->getResource())) {
            abort(403);
        }

        $parentError = null;
        $response = $this->saveWithUploads($request, function () use ($request, &$parentError) {
            // The parent's fields on this form are saved first; when the api
            // refuses those, nothing of this form is saved.
            $parentError = $this->saveParentFormFields($request);
            if ($parentError !== null) {
                return $parentError;
            }

            $apiRequest = $this->transformFormInput($request, Action::EDIT);
            return $this->dispatchToApi(Action::EDIT, $apiRequest);
        });

        if ($parentError !== null) {
            return $parentError;
        }

        if (!($response instanceof ResourceResponse)) {
            return $this->handleErrorResponse($response, 'store');
        }

        return $this->afterUpdate($request, $response->getResource());
    }

    /**
     * Show confirm delete dialog.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function confirmDelete(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, $request);
        $resource = $resource->getResource();

        $view = $this->getView(Action::DESTROY);
        return view($view, [
            'resource' => $resource,
            'action' => $this->action('destroy'),
            'back' => $this->getCancelUrl($request, $resource),
            'returnParameter' => $this->getReturnParameter(),
            'return' => $this->getReturnUrl($request),
            'layout' => $this->layout,
            'chrome' => $this->getPageChrome($request, Action::DESTROY, $resource),
            'question' => $this->getDestroyConfirmText($request, $resource),
            'confirmLabel' => $this->getDestroyConfirmLabel($request, $resource),
        ]);
    }

    /**
     * What the delete page asks. Override for a resource whose delete means
     * something else ("go back to the standard version?").
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getDestroyConfirmText(Request $request, RESTResource $resource): string
    {
        return __('charonfrontend::crud.confirm_delete');
    }

    /**
     * The button that confirms the delete.
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getDestroyConfirmLabel(Request $request, RESTResource $resource): string
    {
        return __('charonfrontend::crud.yes');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function destroy(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, $request)->getResource();
        $response = $this->dispatchToApi(Action::DESTROY, $request);

        // The api may refuse (a 4xx with its reasons): say why instead of "Deleted".
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            return $this->handleErrorResponse($response, 'destroy');
        }

        return $this->afterDestroy($request, $resource);
    }

    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function getTableForResourceCollection (
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        return $this->traitGetTableForResourceCollection($request, $collection, $resourceDefinition, $context);
    }

    /**
     * A table without any actions on it: enough to turn resources into
     * labels, links and cells, which is all a read-only rendering needs.
     *
     * The only place a Table is constructed, and so the only place the
     * relationship resolvers are installed -- both paths that build one
     * (traitGetTableForResourceCollection() and show()'s detail rows) come
     * through here, and they need to keep doing so.
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    protected function makeTable(
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $table = new Table(
            $collection,
            $resourceDefinition,
            $context,
            $request->getRequestUri()
        );

        $table->setResourceUrlResolver(function (RESTResource $related) use ($request) {
            return $this->getRelatedResourceUrl($request, $related);
        });

        $table->setResourceLabelResolver(function (RESTResource $related) use ($request) {
            return $this->getRelatedResourceLabel($request, $related);
        });

        $table->hideColumns($this->getHiddenIndexColumns($request));
        $table->hideFilters($this->getHiddenIndexFilters($request));

        return $table;
    }

    /**
     * Fields (by display name) that get no column in the list, e.g. an
     * identifier that means nothing to the reader.
     * @param Request $request
     * @return string[]
     */
    protected function getHiddenIndexColumns(Request $request): array
    {
        return [];
    }

    /**
     * Filterable fields (by display name) that get no filter above the list.
     * @param Request $request
     * @return string[]
     */
    protected function getHiddenIndexFilters(Request $request): array
    {
        return [];
    }

    /**
     * Add an action of the application to every row of the list, next to
     * View, Edit and Delete:
     *
     * $this->addRowAction('Preview', function (Request $request, RESTResource $mail) {
     *     return action('MailController@preview', [ $this->idOf($mail) ]);
     * });
     *
     * It is a button with its label. Options:
     * - 'style': Bootstrap button style ('primary', 'danger', 'outline-secondary', ...).
     * - 'icon': 'view', 'edit', 'delete' or an icon the app added to the table
     *   views; the action is then an icon button with the label as tooltip.
     * - 'menu' => true: an entry of the row's "more" menu instead of a button.
     * - 'method': 'post', 'put', 'patch' or 'delete'; the action is then a form
     *   (with csrf token) instead of a link.
     * - 'confirm': a question to confirm before the action is done.
     * - 'condition': function (Request, RESTResource): bool; false hides the
     *   action for that row. The url returning null hides it as well.
     * - 'select': [ 'name' => string, 'options' => function (Request, RESTResource): array value => label,
     *   'selected' => function (Request, RESTResource): mixed ]: a select in
     *   front of the button, posted as that name (the method defaults to post).
     *   A row without options gets no action.
     *
     * @param string $label
     * @param callable $url function (Request, RESTResource): ?string
     * @param array $options
     * @return $this
     */
    protected function addRowAction(string $label, callable $url, array $options = [])
    {
        $this->appRowActions[] = [ 'label' => $label, 'url' => $url, 'options' => $options ];
        return $this;
    }

    /**
     * A built-in row action (Action::VIEW, EDIT or DESTROY) before it joins
     * the table: override to draw it differently, e.g.
     * $rowAction->setIcon(null)->showLabel()->setStyle('danger').
     * @param string $action
     * @param ResourceAction $rowAction
     * @return ResourceAction
     */
    protected function configureRowAction(string $action, ResourceAction $rowAction): ResourceAction
    {
        return $rowAction;
    }

    /**
     * @param Request $request
     * @param Table $table
     */
    private function addAppRowActions(Request $request, Table $table): void
    {
        foreach ($this->appRowActions as $appAction) {
            $options = $appAction['options'];
            $url = $appAction['url'];
            $select = $options['select'] ?? null;

            $rowAction = new CallbackAction($appAction['label'], function ($resource) use ($request, $url) {
                return call_user_func($url, $request, $resource);
            });

            if (isset($options['icon'])) {
                $rowAction->setIcon($options['icon']);
            } elseif (empty($options['menu'])) {
                $rowAction->showLabel();
            }

            if (isset($options['style'])) {
                $rowAction->setStyle($options['style']);
            }
            if (isset($options['method'])) {
                $rowAction->setMethod($options['method']);
            }
            if (isset($options['confirm'])) {
                $rowAction->setConfirm($options['confirm']);
            }

            if ($select) {
                $rowAction->setOptions(
                    $select['name'],
                    function ($resource) use ($request, $select) {
                        return call_user_func($select['options'], $request, $resource);
                    },
                    isset($select['selected']) ? function ($resource) use ($request, $select) {
                        return call_user_func($select['selected'], $request, $resource);
                    } : null
                );
            }

            $rowAction->setCondition(function ($resource) use ($request, $options, $url, $select) {
                if (isset($options['condition']) && !call_user_func($options['condition'], $request, $resource)) {
                    return false;
                }
                if ($select && count((array) call_user_func($select['options'], $request, $resource)) === 0) {
                    return false;
                }

                return call_user_func($url, $request, $resource) !== null;
            });

            $table->modelAction($rowAction);
        }
    }

    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    public function traitGetTableForResourceCollection (
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        ContextContract $context
    ): Table {
        $table = $this->makeTable($request, $collection, $resourceDefinition, $context);

        if ($this->hasMethod(Action::VIEW)) {
            $table->modelAction($this->configureRowAction(Action::VIEW,
                (new ResourceAction(
                    $this->getControllerAction(Action::VIEW),
                    $this->getActionText(Action::VIEW, $resourceDefinition),
                    static::getRouteIdParameterName()
                ))
                    ->setRouteParameters($this->getShowRouteParameters($request))
                    ->setQueryParameters($this->getShowQueryParameters($request))
                    ->setIcon('view')
                    ->setCondition(function($model) use ($request) {
                        return $this->canViewModel($request, $model);
                    })
            ));
        }

        if ($this->hasMethod(Action::EDIT)) {
            $table->modelAction($this->configureRowAction(Action::EDIT,
                (new ResourceAction(
                    $this->getControllerAction(Action::EDIT),
                    $this->getActionText(Action::EDIT, $resourceDefinition),
                    static::getRouteIdParameterName()
                ))
                    ->setRouteParameters($this->getEditRouteParameters($request))
                    ->setQueryParameters($this->getEditQueryParameters($request))
                    ->setIcon('edit')
                    ->setCondition(function($model) use ($request) {
                        return $this->canEditModel($request, $model);
                    })
            ));
        }

        if ($this->hasMethod(Action::DESTROY)) {
            $table->modelAction($this->configureRowAction(Action::DESTROY,
                (new ResourceAction(
                    $this->getControllerAction(Action::DESTROY),
                    $this->getActionText(Action::DESTROY, $resourceDefinition),
                    static::getRouteIdParameterName()
                ))
                    ->setRouteParameters($this->getDestroyRouteParameters($request))
                    ->setQueryParameters($this->getDestroyQueryParameters($request))
                    ->setIcon('delete')
                    ->setCondition(function($model) use ($request) {
                        return $this->canDestroyModel($request, $model);
                    })
            ));
        }

        $this->addAppRowActions($request, $table);

        // Now set collection actions too
        if (
            $this->hasMethod(Action::CREATE) &&
            $this->canCreateModel($request, $resourceDefinition)
        ) {
            $table->collectionAction(
                (new CollectionAction(
                    $this->getControllerAction(Action::CREATE),
                    $this->getActionText(Action::CREATE, $resourceDefinition)
                ))
                    ->setRouteParameters($this->getCreateRouteParameters($request))
                    ->setQueryParameters($this->getCreateQueryParameters($request))
            );
        }

        return $table;
    }

    /**
     * @param $resourceDefinitionClassName
     * @param $controllerClassName
     * @return $this
     */
    protected function setChildController($resourceDefinitionClassName, $controllerClassName)
    {
        $this->childControllerMap[$resourceDefinitionClassName] = $controllerClassName;
        return $this;
    }

    /**
     * Instantiate the front controller registered for a resource definition
     * through setChildController(), if any.
     * @param ResourceDefinition $resourceDefinition
     * @return FrontCrudControllerContract|null
     */
    protected function getChildController(ResourceDefinition $resourceDefinition)
    {
        $className = $this->childControllerMap[get_class($resourceDefinition)] ?? null;
        if (!$className) {
            return null;
        }

        if (!isset($this->childControllers[$className])) {
            $childController = new $className;
            if (! ($childController instanceof FrontCrudControllerContract)) {
                abort(500, 'Only controllers implementing FrontCrudControllerContract ' .
                    'can be used for expanding relationships'
                );
            }
            $this->childControllers[$className] = $childController;
        }

        return $this->childControllers[$className];
    }

    /**
     * Url of the show page of a resource that appears as a relationship in
     * one of this controller's tables, or null when there is none: no child
     * controller registered for its definition, viewing not allowed, or a
     * route that needs parameters this request doesn't have (a child mounted
     * under another parent, e.g. /parents/{parent}/children/{id}).
     * @param Request $request
     * @param RESTResource $related
     * @return string|null
     */
    protected function getRelatedResourceUrl(Request $request, RESTResource $related)
    {
        $childController = $this->getChildController($related->getResourceDefinition());
        if (!$childController) {
            return null;
        }

        try {
            return $childController->getShowUrl($request, $related);
        } catch (UrlGenerationException $e) {
            return null;
        }
    }

    /**
     * Label a resource is shown as when it appears as a relationship in one
     * of this controller's tables, or null to let laravel-table decide: a
     * name-like field when the resource has one, its identifier when it
     * doesn't. Override for resources that identify themselves through some
     * other field -- returning null for the ones that don't need it, rather
     * than restating that default.
     *
     * "This controller's tables" is meant literally, and matches how
     * getRelatedResourceUrl() behaves: the child tables show() builds for a
     * ChildrenValue relationship come from the registered child controller,
     * so they carry that controller's labels rather than this one's.
     *
     * $request is unused here and by most overrides, but the signature takes
     * it anyway -- PHP cannot widen a method's arity later without breaking
     * every override, and a label that varies by locale or by the scope the
     * route carries has nowhere else to read it from.
     * @param Request $request
     * @param RESTResource $related
     * @return string|null
     */
    protected function getRelatedResourceLabel(Request $request, RESTResource $related): ?string
    {
        return null;
    }

    /**
     * Url of this controller's show page for a resource, or null when it
     * can't be shown.
     * @param Request $request
     * @param RESTResource $resource
     * @return string|null
     * @throws UrlGenerationException when the route needs parameters the request doesn't carry
     */
    public function getShowUrl(Request $request, RESTResource $resource): ?string
    {
        if (!$this->hasMethod(Action::VIEW) || !$this->canViewModel($request, $resource)) {
            return null;
        }

        $action = (new ResourceAction(
            $this->getControllerAction(Action::VIEW),
            $this->getActionText(Action::VIEW, $resource->getResourceDefinition()),
            static::getRouteIdParameterName()
        ))
            ->setRouteParameters($this->getShowRouteParameters($request))
            ->setQueryParameters($this->getShowQueryParameters($request));

        return $action->getUrl($resource);
    }

    /**
     * Change the layout file that will be used by the crud layout.
     * @param $layout
     * @return $this
     */
    protected function setLayout($layout)
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getIndexRouteParameters(Request $request)
    {
        return $this->getRouteParameters($request, Action::INDEX);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getIndexQueryParameters(Request $request)
    {
        return [];
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getCreateRouteParameters(Request $request)
    {
        return $this->getRouteParameters($request, Action::CREATE);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getCreateQueryParameters(Request $request)
    {
        $parameters = [];

        // Add a return to parameter
        $parameters[$this->getReturnParameter()] = $this->getCurrentPath($request);

        return $parameters;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getDestroyRouteParameters(Request $request)
    {
        return $this->getRouteParameters($request, Action::DESTROY);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getDestroyQueryParameters(Request $request)
    {
        $parameters = [];

        // Add a return to parameter
        $parameters[$this->getReturnParameter()] = $this->getCurrentPath($request);

        return $parameters;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getEditRouteParameters(Request $request)
    {
        return $this->getRouteParameters($request, Action::EDIT);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getEditQueryParameters(Request $request)
    {
        $parameters = [];

        // Add a return to parameter
        $parameters[$this->getReturnParameter()] = $this->getCurrentPath($request);

        return $parameters;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getShowRouteParameters(Request $request)
    {
        return $this->getRouteParameters($request, Action::VIEW);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getShowQueryParameters(Request $request)
    {
        return [];
    }

    /**
     * Path and query string of the current page, to come back to.
     * @param Request $request
     * @return string
     */
    protected function getCurrentPath(Request $request): string
    {
        $query = $request->query();
        unset($query[$this->getReturnParameter()]);

        return '/' . ltrim($request->path(), '/') . (count($query) ? '?' . http_build_query($query) : '');
    }

    /**
     * Create and return the corresponding Api Controller
     * @return ResourceController
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function getApiController()
    {
        if (!isset($this->apiController)) {
            $this->apiController = $this->createApiController();
        }
        return $this->apiController;
    }

    /**
     * @param $action
     * @return mixed
     * @throws UnresolvedMethodException
     */
    protected function resolveMethod($action)
    {
        if (isset($this->routeMap)) {
            if (isset($this->routeMap[$action])) {
                return $this->routeMap[$action];
            }
        }

        throw UnresolvedMethodException::create($this->getApiController(), $action);
    }

    /**
     * @param $action
     * @return bool
     */
    protected function hasMethod($action)
    {
        try {
            $this->resolveMethod($action);
            return true;
        } catch (UnresolvedMethodException $e) {
            return false;
        }
    }

    /**
     * Dispatch an action to the API.
     * @param $action
     * @param Request $request
     * @param array $parameters
     * @return ResourceResponse|Response
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function dispatchToApi($action, Request $request, $parameters = [])
    {
        $requestParameters = $this->getApiControllerParameters($request, $action);
        if ($requestParameters !== null) {
            foreach ($requestParameters as $k => $v) {
                $request->route()->setParameter($k, $v);
            }
        }

        // Also translate the id parameter
        $this->translateIdParameter($request, $action);

        $method = $this->resolveMethod($action);
        return $this->callApiMethod($method, $request, $parameters);
    }

    /**
     * Call a method on the API Controller.
     * @param $method
     * @param Request $request
     * @param array $parameters
     * @return mixed
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function callApiMethod($method, Request $request, $parameters = [])
    {
        $controller = $this->getApiController();

        array_unshift($parameters, $request);
        return call_user_func_array([ $controller, $method ], $parameters);
    }

    /**
     * @return mixed
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function getResourceDefinition()
    {
        return $this->getApiController()->getResourceDefinitionFactory()->getDefault();
    }

    /**
     * @param $action
     * @return string
     */
    protected function getView($action)
    {
        return 'charonfrontend::crud.' . $action;
    }

    /**
     * @param $label
     * @param $url
     * @return array
     */
    protected function getAction($label, $url = null)
    {
        if (!isset($url)) {
            $url = $this->action($label);
        }

        return [
            'label' => $label,
            'url' => $url
        ];
    }

    /**
     * Generate an url for a method of this controller.
     * @param $method
     * @param array $parameters
     * @return string
     */
    protected function action($method, $parameters = [])
    {
        $parameters = array_merge($parameters, \Request::route()->parameters());
        return action($this->getRawControllerAction($method), $parameters);
    }

    /**
     * Get the name of the action
     * @param $method
     * @return string
     */
    protected function getControllerAction($method)
    {
        if (!isset($this->frontControllerRouteMap[$method])) {
            throw new \InvalidArgumentException("Action " . $method . " does not exist in frontControllerRouteMap");
        }

        $action = $this->frontControllerRouteMap[$method];
        return $this->getRawControllerAction($action);
    }

    /**
     * @param $action
     * @return string
     */
    protected function getRawControllerAction($action)
    {
        return '\\' . static::class . '@' . $action;
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getApiControllerParameters(Request $request, $method)
    {
        return [];
    }

    /**
     * @param Request $request
     * @param $method
     * @return void
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function translateIdParameter(Request $request, $method)
    {
        $apiIdName = static::getApiRouteIdParameterName();
        if (!$apiIdName) {
            return;
        }

        $id = $request->route(static::getRouteIdParameterName());

        if ($id) {
            $request->route()->setParameter($apiIdName, $id);
        }
    }

    /**
     * @param $method
     * @return \Illuminate\Routing\Route|null
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function getApiRoute($method)
    {
        $controllerAction = $this->resolveMethod($method);
        $controller = get_class($this->getApiController());

        return Route::getRoutes()->getByAction($controller . '@' . $controllerAction);
    }

    /**
     * Get any parameters that might be required by the controller.
     * @param Request $request
     * @param $method
     * @return array
     */
    protected function getRouteParameters(Request $request, $method)
    {
        return [];
    }

    /**
     * @param $action
     * @param $processmethod
     * @param ResourceResponse|Response $model
     * @return \Illuminate\Http\Response|View
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    protected function formView($action, $processmethod, $response = null)
    {
        $resource = null;
        if ($response instanceof ResourceResponse) {
            $resource = $response->getResource();
        }

        $request = \Request::instance();

        $formFields = [];
        $multipart = false;
        foreach ($this->getFormFields($action, $resource) as $formField) {
            $widget = $this->widgets()->resolve($formField->getField());
            $options = $this->resolveFieldOptions($request, $formField, $widget);
            if ($resource && $formField->getField() instanceof RelationshipField) {
                $options = $this->withLinkedOptions($request, $resource, $formField->getField(), $options);
            }
            $formField->setOptions($options);

            $formFields[] = [
                'field' => $formField,
                'widget' => $widget,
            ];

            $multipart = $multipart || $widget->isMultipart();
        }

        switch ($processmethod) {

            case 'update':
                $method = 'put';
                $verb = 'save';
                break;

            case 'store':
            default:
                $method = 'post';
                $verb = 'create';
                break;
        }

        $formSections = $this->groupFormFields($formFields);

        if ($action === Action::EDIT && $this->parentFormGroup) {
            $parentSection = $this->getParentFormSection($request);
            if ($parentSection !== null) {
                $formSections[] = $parentSection;
                foreach ($parentSection['fields'] as $parentField) {
                    $multipart = $multipart || $parentField['widget']->isMultipart();
                }
            }
        }

        return $this->createFormView($action, $this->getView('form'), [
            'formFields' => $formFields,
            'formSections' => $formSections,
            'tabs' => $resource || $action === Action::CREATE ? $this->getFormTabs($request, $action, $resource) : [],
            'saveButtons' => $this->saveButtons,
            'saveButtonName' => 'charon_save',
            'multipart' => $multipart,
            'action' => $this->action($processmethod),
            'resource' => $resource,
            'verb' => $verb,
            'method' => $method,
            'layout' => $this->layout,
            'chrome' => $this->getPageChrome($request, $action, $resource),
            'cancel' => $this->getCancelUrl($request, $resource),
            'returnParameter' => $this->getReturnParameter(),
            'return' => $this->getReturnUrl($request),
        ]);
    }

    /**
     * Show a set of fields together under a heading on create and edit
     * forms, e.g. $this->addFieldGroup('Tickets', [ 'vat_percentage', 'team_size' ]).
     * A group sits where the first of its fields would be; fields in no
     * group keep their definition order. Names are field display names.
     *
     * Options:
     * - 'aside' => true: in the side column (on wide screens; below the
     *   other fields on a phone). A form with aside groups has two columns.
     * - 'collapsed' => true: folded to its heading until it is opened
     *   (opened anyway when one of its fields has an error).
     * - 'description' => string: a line under the heading.
     * @param string $label
     * @param string[] $fieldNames
     * @param array $options
     * @return $this
     */
    protected function addFieldGroup(string $label, array $fieldNames, array $options = [])
    {
        $this->fieldGroups[] = $this->fieldGroupOptions($options) + [ 'label' => $label, 'fields' => array_values($fieldNames) ];
        return $this;
    }

    /**
     * @param array $options
     * @return array
     */
    private function fieldGroupOptions(array $options): array
    {
        return [
            'aside' => !empty($options['aside']),
            'collapsed' => !empty($options['collapsed']),
            'description' => isset($options['description']) ? (string) $options['description'] : null,
        ];
    }

    /**
     * Edit the parent (see setParentController()) on this controller's edit
     * form too: the fields of the parent's own edit form, under a heading,
     * saved together with this form (the parent first). E.g. a page's
     * settings next to the content of one of its translations:
     * $this->editParentOnForm('Pagina', [ 'aside' => true, 'description' => 'Geldt voor alle talen.' ]).
     * Takes the options of addFieldGroup(). Only shown when the parent may
     * be edited.
     * @param string $label
     * @param array $options
     * @return $this
     */
    protected function editParentOnForm(string $label, array $options = [])
    {
        $this->parentFormGroup = [ 'label' => $label, 'options' => $options ];
        return $this;
    }

    /**
     * Another submit button next to Save, e.g. "Save and view". The
     * form submits $value as charon_save; read it in
     * redirectAfterSave() with getPressedSaveButton().
     * @param string $label
     * @param string $value
     * @return $this
     */
    protected function addSaveButton(string $label, string $value)
    {
        $this->saveButtons[] = [ 'label' => $label, 'value' => $value ];
        return $this;
    }

    /**
     * The value of the save button that submitted the form (addSaveButton()),
     * null for the standard one.
     * @param Request $request
     * @return string|null
     */
    protected function getPressedSaveButton(Request $request): ?string
    {
        $value = $request->input('charon_save');
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Tabs above a create or edit form, e.g. one per language of a
     * translated resource. Empty by default.
     * @param Request $request
     * @param string $action Action::CREATE or Action::EDIT
     * @param RESTResource|null $resource
     * @return array [ [ 'label' => string, 'url' => string, 'active' => bool, 'badge' => ?string, 'muted' => bool ] ]
     */
    protected function getFormTabs(Request $request, string $action, ?RESTResource $resource = null): array
    {
        return [];
    }

    /**
     * The parent's form section on this controller's edit form, null when
     * there is none or it may not be edited.
     * @param Request $request
     * @return array|null
     */
    private function getParentFormSection(Request $request): ?array
    {
        $parent = $this->getParentController();
        if (!$parent || !$this->parentFormGroup || !method_exists($parent, 'getFormFieldsForChildForm')) {
            return null;
        }

        $fields = $parent->getFormFieldsForChildForm($request, $request->route($this->parentController['parameter']));
        if ($fields === null || count($fields) === 0) {
            return null;
        }

        return $this->fieldGroupOptions($this->parentFormGroup['options']) + [
            'label' => $this->parentFormGroup['label'],
            'fields' => $fields,
        ];
    }

    /**
     * Save the parent's fields of this form, if it has them.
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response|null The response to give when the parent was not saved.
     */
    private function saveParentFormFields(Request $request)
    {
        $parent = $this->getParentController();
        if (!$parent || !$this->parentFormGroup || !method_exists($parent, 'saveFromChildForm') || !is_array($request->input('parent'))) {
            return null;
        }

        return $parent->saveFromChildForm($request, $request->route($this->parentController['parameter']));
    }

    /**
     * This controller's edit form fields for one of its resources, to be
     * shown on a child's edit form (editParentOnForm()), with inputs under
     * parent[...]. Null when the resource may not be edited.
     * @param Request $request The child's request.
     * @param mixed $id
     * @return array|null [ [ 'field' => FormField, 'widget' => Widget ] ]
     */
    public function getFormFieldsForChildForm(Request $request, $id): ?array
    {
        $viewRequest = $this->makeSubRequest($request, $id);

        $response = $this->dispatchToApi(Action::VIEW, $viewRequest);
        if (!($response instanceof ResourceResponse)) {
            return null;
        }
        $resource = $response->getResource();
        if (!$this->isMethodAllowed($viewRequest, Action::EDIT, $resource)) {
            return null;
        }

        return $this->withFormRoot('parent', function () use ($viewRequest, $resource) {
            $out = [];
            foreach ($this->getFormFields(Action::EDIT, $resource) as $formField) {
                $widget = $this->widgets()->resolve($formField->getField());
                $options = $this->resolveFieldOptions($viewRequest, $formField, $widget);
                if ($formField->getField() instanceof RelationshipField) {
                    $options = $this->withLinkedOptions($viewRequest, $resource, $formField->getField(), $options);
                }
                $formField->setOptions($options);
                $out[] = [ 'field' => $formField, 'widget' => $widget ];
            }
            return $out;
        });
    }

    /**
     * Save one of this controller's resources from the parent[...]
     * input of a child's form (editParentOnForm()).
     * @param Request $request The child's request.
     * @param mixed $id
     * @return \Symfony\Component\HttpFoundation\Response|null Null when saved, else the response to give (back to the form, with the errors under the parent's fields).
     */
    public function saveFromChildForm(Request $request, $id)
    {
        $viewRequest = $this->makeSubRequest($request, $id);
        $current = $this->dispatchToApi(Action::VIEW, $viewRequest);
        if (!($current instanceof ResourceResponse)) {
            return $current;
        }
        if (!$this->isMethodAllowed($viewRequest, Action::EDIT, $current->getResource())) {
            abort(403);
        }

        return $this->withFormRoot('parent', function () use ($request, $id) {
            $input = $this->transformFormInput($request, Action::EDIT);

            $editRequest = $this->makeSubRequest($request, $id);
            $editRequest->setMethod('PUT');
            $editRequest->replace($input->all());

            $response = $this->dispatchToApi(Action::EDIT, $editRequest);
            if ($response instanceof ResourceResponse) {
                return null;
            }

            return $response instanceof JsonResponse
                ? $this->handleErrorResponse($response, 'store')
                : $response;
        });
    }

    /**
     * @param string $root
     * @param callable $callback
     * @return mixed
     */
    private function withFormRoot(string $root, callable $callback)
    {
        $previous = $this->formRoot;
        $this->formRoot = $root;
        try {
            return $callback();
        } finally {
            $this->formRoot = $previous;
        }
    }

    /**
     * Split the form fields into sections: runs of ungrouped fields (label
     * null) and the groups registered with addFieldGroup(). Groups whose
     * fields aren't on this form are left out.
     * @param array $formFields [ [ 'field' => FormField, 'widget' => Widget ] ]
     * @return array [ [ 'label' => ?string, 'fields' => array ] ]
     */
    protected function groupFormFields(array $formFields): array
    {
        $groupOf = [];
        foreach ($this->fieldGroups as $index => $group) {
            foreach ($group['fields'] as $name) {
                if (!isset($groupOf[$name])) {
                    $groupOf[$name] = $index;
                }
            }
        }

        $byName = [];
        foreach ($formFields as $formField) {
            $byName[$formField['field']->getField()->getDisplayName()] = $formField;
        }

        $sections = [];
        $placed = [];
        foreach ($formFields as $formField) {
            $name = $formField['field']->getField()->getDisplayName();
            if (!isset($groupOf[$name])) {
                $last = count($sections) - 1;
                if ($last >= 0 && $sections[$last]['label'] === null) {
                    $sections[$last]['fields'][] = $formField;
                } else {
                    $sections[] = $this->fieldGroupOptions([]) + [ 'label' => null, 'fields' => [ $formField ] ];
                }
                continue;
            }

            $index = $groupOf[$name];
            if (isset($placed[$index])) {
                continue;
            }
            $placed[$index] = true;

            $fields = [];
            foreach ($this->fieldGroups[$index]['fields'] as $groupField) {
                if (isset($byName[$groupField]) && $groupOf[$groupField] === $index) {
                    $fields[] = $byName[$groupField];
                }
            }
            $sections[] = $this->fieldGroups[$index] + [];
            $sections[count($sections) - 1]['fields'] = $fields;
        }

        return $sections;
    }

    /**
     * The widget registry of this controller. Register widgets in the
     * constructor: $this->widgets()->forField('description', new TextareaWidget()).
     * @return WidgetRegistry
     */
    protected function widgets(): WidgetRegistry
    {
        if (!isset($this->widgetRegistry)) {
            $this->widgetRegistry = new WidgetRegistry();
        }
        return $this->widgetRegistry;
    }

    /**
     * The fields a create or edit form shows, in definition order: the
     * writeable fields, and the relationships that can link existing
     * resources.
     * @param string $action Action::CREATE or Action::EDIT
     * @param RESTResource|null $resource
     * @return FormField[]
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    protected function getFormFields($action, ?RESTResource $resource = null): array
    {
        $context = new Context($action, []);

        $resourceDefinition = $resource
            ? $resource->getResourceDefinition()
            : $this->getResourceDefinition();

        $fields = $resourceDefinition
            ->getFields()
            ->filter(
                function (Field $field) use ($context) {
                    if ($field instanceof IdentifierField) {
                        return false;
                    }
                    if ($field instanceof RelationshipField) {
                        return $field->canLinkExistingEntities($context);
                    }
                    return true;
                }
            )
            ->getWithAction($context->getAction());

        $out = [];
        foreach ($fields as $field) {
            $out[] = new FormField($field, $resource, $this->formRoot);
        }
        return $out;
    }

    /**
     * Options of a field: the allowed values of an enum field, the
     * resources that can be linked for a relationship, none otherwise.
     * @param Request $request
     * @param FormField $formField
     * @param Widget $widget
     * @return string[] Label by value.
     */
    protected function resolveFieldOptions(Request $request, FormField $formField, Widget $widget): array
    {
        $field = $formField->getField();

        if ($widget instanceof ProvidesOptions) {
            return $widget->getOptions($request, $formField);
        }

        if ($field instanceof RelationshipField) {
            return $this->resolveLinkableOptions($request, $field);
        }

        if ($field instanceof ResourceField) {
            $out = [];
            foreach (($field->getAllowedValues() ?? []) as $value) {
                $out[$value] = $value;
            }
            return $out;
        }

        return [];
    }

    /**
     * Add the resources a relationship links to now, when the option list
     * doesn't offer them (beyond the option limit, another organisation,
     * ...). Without them, saving the form would silently unlink them.
     * @param Request $request
     * @param RESTResource $resource
     * @param RelationshipField $field
     * @param array $options
     * @return array
     */
    protected function withLinkedOptions(Request $request, RESTResource $resource, RelationshipField $field, array $options): array
    {
        $property = $resource->getProperties()->getProperty($field);
        if ($property instanceof ChildValue) {
            $linked = $property->getChild() ? [ $property->getChild() ] : [];
        } elseif ($property instanceof ChildrenValue) {
            $linked = $property->getChildren();
        } else {
            return $options;
        }

        $missing = [];
        foreach ($linked as $child) {
            $identifiers = $child->getIdentifiers()->getValues();
            if (!count($identifiers)) {
                continue;
            }
            $id = $identifiers[0]->getValue();
            if (!array_key_exists($id, $options)) {
                $missing[$id] = $this->getRelatedLabel($request, $child);
            }
        }

        return $missing + $options;
    }

    /**
     * The resources a relationship can link to, label by identifier.
     *
     * When a child controller is registered for the related definition,
     * they come from that controller's api index -- the same list, scope
     * and authorization as its own index page. Otherwise resolveValues()
     * lists every entity of the related model; override one of the two
     * when that is too much.
     * @param Request $request
     * @param RelationshipField $field
     * @return string[]
     */
    protected function resolveLinkableOptions(Request $request, RelationshipField $field): array
    {
        $childController = $this->getChildController($field->getChildResourceDefinition());
        if ($childController) {
            return $childController->getLinkableOptions($request);
        }

        return $this->resolveValues($field);
    }

    /**
     * This controller's resources as options for a relationship in another
     * controller's form: label by identifier, from the api index (scoped
     * and authorized as the index is), at most LINKABLE_OPTIONS_LIMIT.
     * Empty when the index isn't allowed.
     * @param Request $request The request of the form that shows them.
     * @return string[]
     */
    public function getLinkableOptions(Request $request): array
    {
        if (!$this->hasMethod(Action::INDEX)) {
            return [];
        }

        $route = $request->route() ? clone $request->route() : null;

        $indexRequest = Request::create($request->getPathInfo(), 'GET', [
            'records' => static::getLinkableOptionsLimit(),
        ]);
        $indexRequest->setUserResolver($request->getUserResolver());
        $indexRequest->setRouteResolver(function () use ($route) {
            return $route;
        });
        if ($request->hasSession()) {
            $indexRequest->setLaravelSession($request->session());
        }

        try {
            $response = $this->dispatchToApi(Action::INDEX, $indexRequest);
        } catch (AuthorizationException $e) {
            return [];
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 403 || $e->getStatusCode() === 404) {
                return [];
            }
            throw $e;
        }

        if (!($response instanceof ResourceResponse) || !($response->getResource() instanceof ResourceCollection)) {
            return [];
        }

        $out = [];
        foreach ($response->getResource() as $resource) {
            $identifiers = $resource->getIdentifiers()->getValues();
            if (count($identifiers) === 0) {
                continue;
            }
            $out[$identifiers[0]->getValue()] = $this->getLinkableOptionLabel($request, $resource);
        }
        return $out;
    }

    /**
     * @return int
     */
    protected static function getLinkableOptionsLimit(): int
    {
        return 500;
    }

    /**
     * Label of a resource of this controller in a linkable picker: the
     * controller's own getRelatedResourceLabel() when it has one, else a
     * name-like field, else the identifier.
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getLinkableOptionLabel(Request $request, RESTResource $resource): string
    {
        $label = $this->getRelatedResourceLabel($request, $resource);
        if ($label !== null) {
            return $label;
        }

        $values = $resource->toArray();
        foreach ([ 'name', 'title', 'label' ] as $candidate) {
            if (isset($values[$candidate]) && is_scalar($values[$candidate]) && $values[$candidate] !== '') {
                return (string) $values[$candidate];
            }
        }

        return '#' . $resource->getIdentifiers()->getValues()[0]->getValue();
    }

    /**
     * @param string $action
     * @param string $view
     * @param array $properties
     * @return \Illuminate\View\View
     */
    protected function createFormView($action, $view, $properties)
    {
        return view($view, $properties);
    }

    /**
     * @return array
     */
    protected function getAdditionalFormViewProperties()
    {
        return [];
    }

    /**
     * @param RelationshipField $field
     * @return string[]
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     */
    protected function resolveValues(RelationshipField $field)
    {
        $childResource = $field->getChildResource();
        $entity = $childResource->getEntityClassName();

        // fetch everything.
        $entities = call_user_func([ $entity, 'all']);

        // @TODO this is wrong, but it works as long as the identifier is called "id" and the name is called "name".
        $out = [];
        foreach ($entities as $entity) {
            $out[$entity->id] = $entity->name;
        }
        return $out;
    }

    /**
     * Executed after store.
     * @param Request $request
     * @param RESTResource $newResource
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function afterStore(Request $request, RESTResource $newResource)
    {
        Session::flash('message', __('charonfrontend::crud.created', [ 'entity' => $this->getEntityLabel() ]));

        return $this->redirectAfterSave($request, $newResource);
    }

    /**
     * Executed after store.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function afterUpdate(Request $request, RESTResource $resource)
    {
        Session::flash('message', __('charonfrontend::crud.saved'));

        return $this->redirectAfterSave($request, $resource);
    }

    /**
     * Executed after destroy.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function afterDestroy(Request $request, RESTResource $resource)
    {
        Session::flash('message', __('charonfrontend::crud.deleted'));

        return $this->redirectAfterDestroy($request, $resource);
    }

    /**
     * Read the form and save it through the API. File widgets store their
     * uploads while the form is read; when the API refuses the save (or
     * anything throws), those are discarded again and the uploaded files
     * are kept for the next try, so nothing unused stays behind and the
     * user doesn't have to pick their file again.
     *
     * @param Request $request
     * @param callable $save function (): ResourceResponse|Response
     * @return mixed
     */
    protected function saveWithUploads(Request $request, callable $save)
    {
        $uploads = app(PendingUploads::class);

        try {
            $response = $save();
        } catch (\Throwable $e) {
            $uploads->rollback($request);
            throw $e;
        }

        if ($response instanceof ResourceResponse) {
            $uploads->commit($request);
        } else {
            $uploads->rollback($request);
        }

        return $response;
    }

    /**
     * @param JsonResponse $response
     * @param $redirectMethod
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleErrorResponse(JsonResponse $response, $redirectMethod)
    {
        $data = $response->getData(true);
        $error = is_array($data['error'] ?? null) ? $data['error'] : [];

        // What the api says about a field of the form goes under that
        // field (errors of "fields.<name>", shown by the widgets' label);
        // everything else in one message above the page.
        $formFields = [];
        if ($redirectMethod !== 'destroy') {
            try {
                foreach ($this->getFormFields(Action::CREATE) as $formField) {
                    $formFields[$formField->getName()] = $formField;
                }
                foreach ($this->getFormFields(Action::EDIT) as $formField) {
                    $formFields[$formField->getName()] = $formField;
                }
            } catch (\Exception $e) {
                $formFields = [];
            }
        }

        $fieldErrors = [];
        $other = [];
        foreach (($error['issues'] ?? []) as $property => $issues) {
            foreach ((array) $issues as $issue) {
                if (!is_scalar($issue) || (string) $issue === '') {
                    continue;
                }

                $name = $this->issueFieldName((string) $property, $formFields);
                if ($name !== null) {
                    $fieldErrors[$formFields[$name]->getDotName()][] = (string) $issue;
                } else {
                    $other[] = (string) $issue;
                }
            }
        }

        $redirect = redirect()->back()->withInput();

        if (count($fieldErrors) > 0) {
            $redirect->withErrors($fieldErrors);
        }

        if (count($other) === 0 && count($fieldErrors) === 0) {
            $other[] = (string) ($error['message'] ?? $response->getStatusCode());
        }

        if (count($other) > 0) {
            $redirect->with('message', new HtmlString(implode('<br>', array_map('e', $other))));
        }

        return $redirect;
    }

    /**
     * The form field an issue of the api is about: the property itself, or
     * the field a nested property ("blocks.0.title") belongs to.
     * @param string $property
     * @param FormField[] $formFields by name
     * @return string|null
     */
    private function issueFieldName(string $property, array $formFields): ?string
    {
        if (isset($formFields[$property])) {
            return $property;
        }

        $first = explode('.', $property)[0];
        return isset($formFields[$first]) ? $first : null;
    }

    /**
     * Translate form input into Charon input: every form field's widget
     * reads its own input.
     * @param Request $request
     * @param string|null $action Action::CREATE or Action::EDIT; guessed from the http method when null.
     * @return Request
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function transformFormInput(Request $request, $action = null)
    {
        if ($action === null) {
            $action = $request->isMethod('post') ? Action::CREATE : Action::EDIT;
        }

        $out = [];
        foreach ($this->getFormFields($action) as $formField) {
            $widget = $this->widgets()->resolve($formField->getField());
            $out[$formField->getName()] = $widget->read($request, $formField);
        }

        $newRequest = $request->duplicate();
        $newRequest->replace($out);

        return $newRequest;
    }

    /**
     * Add a tab to this controller's show pages that links to a page of the
     * app, e.g. an event's waiting list:
     * $this->addTab('waitinglist', 'Waiting list', function (Request $request, RESTResource $event) {
     *     return action('Admin\\WaitingListController@index', [ 'event' => $event->getIdentifiers()->getValues()[0]->getValue() ]);
     * });
     * That page shows the same header and tabs with getHubChrome().
     * @param string $key
     * @param string $label
     * @param callable $url function (Request, RESTResource): ?string; null hides the tab.
     * @return $this
     */
    protected function addTab(string $key, string $label, callable $url)
    {
        $this->appTabs[$key] = [ 'label' => $label, 'url' => $url ];
        return $this;
    }

    /**
     * The tabs of a show page: Details, one per owned child list, then the
     * app's tabs. Keyed by tab key; each has a label and a url.
     * @param Request $request
     * @param RESTResource $resource
     * @param array $childTabs [ key => [ 'label' => string ] ]
     * @return array
     */
    protected function getShowTabs(Request $request, RESTResource $resource, array $childTabs = []): array
    {
        $showUrl = $this->safeShowUrl($request, $resource);
        $tabUrl = function (string $key) use ($showUrl) {
            if ($showUrl === null) {
                return null;
            }
            return $key === 'details' ? $showUrl : $showUrl . (strpos($showUrl, '?') === false ? '?' : '&') . 'tab=' . urlencode($key);
        };

        $tabs = [
            'details' => [ 'label' => __('charonfrontend::crud.details'), 'url' => $tabUrl('details') ],
        ];

        foreach ($childTabs as $key => $tab) {
            $tabs[$key] = [ 'label' => $tab['label'], 'url' => $tabUrl($key) ];
        }

        foreach ($this->appTabs as $key => $tab) {
            $url = call_user_func($tab['url'], $request, $resource);
            if ($url !== null) {
                $tabs[$key] = [ 'label' => $tab['label'], 'url' => $url ];
            }
        }

        return $tabs;
    }

    /**
     * @param Request $request
     * @param array $tabs
     * @return string
     */
    protected function getActiveTab(Request $request, array $tabs): string
    {
        $tab = $request->query('tab');
        return is_string($tab) && isset($tabs[$tab]) && !isset($this->appTabs[$tab]) ? $tab : 'details';
    }

    /**
     * Header and tabs of one of this controller's resources, for an app page
     * that is one of its tabs (see addTab()): the resource's title, its
     * breadcrumbs plus the tab, its actions, and the tabs with $tab active.
     * Render it with @include('charonfrontend::crud.hub-header', [ 'hub' => $hub ]) when it isn't null.
     * @param Request $request The app page's request.
     * @param mixed $id
     * @param string $tab
     * @return array|null [ 'chrome' => array, 'tabs' => array ], or null when the
     *                    user may not view the resource here.
     */
    public function getHubChrome(Request $request, $id, string $tab): ?array
    {
        $viewRequest = $this->makeSubRequest($request, $id);
        try {
            $response = $this->dispatchToApi(Action::VIEW, $viewRequest);
        } catch (AuthorizationException $e) {
            return null;
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 403 || $e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        $resource = $response instanceof ResourceResponse ? $response->getResource() : null;
        if (!($resource instanceof RESTResource)) {
            return null;
        }

        $childTabs = [];
        foreach ($resource->getProperties()->getRelationships()->getValues() as $relationship) {
            $field = $relationship->getField();
            if (!($relationship instanceof ChildrenValue)
                || $field->canLinkExistingEntities(new Context(Action::EDIT, []))
                || !$this->getChildController($field->getChildResourceDefinition())
            ) {
                continue;
            }
            $childTabs[$field->getDisplayName()] = [ 'label' => (new FormField($field))->getLabel() ];
        }

        $tabs = $this->getShowTabs($viewRequest, $resource, $childTabs);
        foreach ($tabs as $key => $value) {
            $tabs[$key]['active'] = $key === $tab;
        }

        $chrome = $this->getPageChrome($viewRequest, Action::VIEW, $resource);
        $last = count($chrome['breadcrumbs']) - 1;
        $chrome['breadcrumbs'][$last]['url'] = $this->safeShowUrl($viewRequest, $resource);
        $chrome['breadcrumbs'][] = [ 'label' => $tabs[$tab]['label'] ?? Str::ucfirst($tab), 'url' => null ];

        return [ 'chrome' => $chrome, 'tabs' => $tabs ];
    }

    /**
     * Label of a related resource: getRelatedResourceLabel(), else a
     * name-like field, else its identifier.
     * @param Request $request
     * @param RESTResource $related
     * @return string
     */
    protected function getRelatedLabel(Request $request, RESTResource $related): string
    {
        $label = $this->getRelatedResourceLabel($request, $related);
        if ($label !== null) {
            return $label;
        }

        $values = $related->toArray();
        foreach ([ 'name', 'title', 'label' ] as $candidate) {
            if (isset($values[$candidate]) && is_scalar($values[$candidate]) && $values[$candidate] !== '') {
                return (string) $values[$candidate];
            }
        }

        $identifiers = $related->getIdentifiers()->getValues();
        return count($identifiers) ? '#' . $identifiers[0]->getValue() : '';
    }

    /**
     * Nest this controller under another front controller, e.g. ticket
     * categories under events at /events/{event}/ticketCategories: the
     * breadcrumbs then start with the parent's ("Events › Quiz night"), and
     * saving a child created or edited from the parent's page goes back to
     * that page.
     * @param string $controllerClassName The parent front controller.
     * @param string $routeParameter The route parameter of this controller's routes that holds the parent's id.
     * @return $this
     */
    protected function setParentController(string $controllerClassName, string $routeParameter)
    {
        $this->parentController = [
            'controller' => $controllerClassName,
            'parameter' => $routeParameter,
        ];
        return $this;
    }

    /**
     * Title, breadcrumbs and actions shown above every crud page
     * (charonfrontend::crud.chrome).
     * @param Request $request
     * @param string $action Action::INDEX, VIEW, CREATE, EDIT or DESTROY
     * @param RESTResource|null $resource
     * @return array [ 'title' => string, 'breadcrumbs' => [ [ 'label', 'url' ] ], 'actions' => [ [ 'label', 'url', 'style' ] ] ]
     */
    protected function getPageChrome(Request $request, string $action, ?RESTResource $resource = null): array
    {
        switch ($action) {
            case Action::INDEX:
                $title = $this->getIndexTitle();
                break;

            case Action::CREATE:
                $title = __('charonfrontend::crud.new_entity', [ 'entity' => $this->getEntityLabel() ]);
                break;

            default:
                $title = $resource ? $this->getResourceTitle($request, $resource) : Str::ucfirst($this->getEntityLabel());
                break;
        }

        return [
            'title' => $title,
            'breadcrumbs' => $this->getBreadcrumbs($request, $action, $resource),
            'actions' => $this->getPageActions($request, $action, $resource),
        ];
    }

    /**
     * Title of the index page: the entity name in plural ("Ticket categories").
     * @return string
     */
    protected function getIndexTitle(): string
    {
        return Str::ucfirst(Str::plural($this->getEntityLabel()));
    }

    /**
     * The entity name in words, lower case ("ticket category").
     * @return string
     */
    protected function getEntityLabel(): string
    {
        return $this->entityLabelOf($this->getResourceDefinition());
    }

    /**
     * A definition's entity name in words, lower case.
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function entityLabelOf(ResourceDefinition $resourceDefinition): string
    {
        return str_replace('_', ' ', Str::snake($resourceDefinition->getEntityName()));
    }

    /**
     * Title of a resource's show, edit and delete pages; the same label the
     * resource gets in linkable pickers.
     * @param Request $request
     * @param RESTResource $resource
     * @return string
     */
    protected function getResourceTitle(Request $request, RESTResource $resource): string
    {
        return $this->getLinkableOptionLabel($request, $resource);
    }

    /**
     * The breadcrumbs of a page: the parent's trail (see
     * setParentController()), this controller's index, the resource, and the
     * page itself when it isn't the resource's show page. The last crumb has
     * no url.
     * @param Request $request
     * @param string $action
     * @param RESTResource|null $resource
     * @return array
     */
    protected function getBreadcrumbs(Request $request, string $action, ?RESTResource $resource = null): array
    {
        $crumbs = $this->getParentBreadcrumbs($request);

        $crumbs[] = [
            'label' => $this->getIndexTitle(),
            'url' => $this->getIndexUrl($request),
        ];

        if ($resource && $action !== Action::INDEX && $action !== Action::CREATE) {
            $crumbs[] = [
                'label' => $this->getResourceTitle($request, $resource),
                'url' => $this->safeShowUrl($request, $resource),
            ];
        }

        switch ($action) {
            case Action::CREATE:
                $crumbs[] = [ 'label' => __('charonfrontend::crud.new'), 'url' => null ];
                break;

            case Action::EDIT:
                $crumbs[] = [ 'label' => $this->getEditActionLabel($this->getResourceDefinition()), 'url' => null ];
                break;

            case Action::DESTROY:
                $crumbs[] = [ 'label' => $this->getDestroyActionLabel($this->getResourceDefinition()), 'url' => null ];
                break;
        }

        $crumbs[count($crumbs) - 1]['url'] = null;
        return $crumbs;
    }

    /**
     * Breadcrumbs of the parent controller's resource this page is nested
     * under: its index and the resource itself. Empty without a parent.
     * @param Request $request
     * @return array
     */
    protected function getParentBreadcrumbs(Request $request): array
    {
        $parent = $this->getParentController();
        if (!$parent) {
            return [];
        }

        $id = $request->route($this->parentController['parameter']);
        return $parent->getBreadcrumbTrail($request, $id);
    }

    /**
     * Breadcrumbs that lead to one of this controller's resources (by id) or,
     * without an id, to its index; for the pages of a child controller.
     * @param Request $request The child page's request.
     * @param mixed $id
     * @return array
     */
    public function getBreadcrumbTrail(Request $request, $id = null): array
    {
        $viewRequest = $this->makeSubRequest($request, $id);

        $crumbs = $this->getParentBreadcrumbs($viewRequest);
        $crumbs[] = [
            'label' => $this->getIndexTitle(),
            'url' => $this->getIndexUrl($viewRequest),
        ];

        if ($id === null) {
            return $crumbs;
        }

        $resource = null;
        try {
            $response = $this->dispatchToApi(Action::VIEW, $viewRequest);
            if ($response instanceof ResourceResponse && $response->getResource() instanceof RESTResource) {
                $resource = $response->getResource();
            }
        } catch (AuthorizationException $e) {
            // Shown without a label or a link below.
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 403 && $e->getStatusCode() !== 404) {
                throw $e;
            }
        }

        $crumbs[] = [
            'label' => $resource ? $this->getResourceTitle($viewRequest, $resource) : '#' . $id,
            'url' => $resource ? $this->safeShowUrl($viewRequest, $resource) : null,
        ];

        return $crumbs;
    }

    /**
     * The buttons above a page: Edit and Delete on a show page.
     * Override (and merge with the parent's result) to add app actions.
     * @param Request $request
     * @param string $action
     * @param RESTResource|null $resource
     * @return array [ [ 'label' => string, 'url' => string, 'style' => 'primary'|'secondary'|'danger' ] ]
     */
    protected function getPageActions(Request $request, string $action, ?RESTResource $resource = null): array
    {
        $actions = [];
        if ($action !== Action::VIEW || !$resource) {
            return $actions;
        }

        $definition = $this->getResourceDefinition();
        $back = [ $this->getReturnParameter() => $this->getCurrentPath($request) ];

        if ($this->hasMethod(Action::EDIT) && $this->canEditModel($request, $resource)) {
            $actions[] = [
                'label' => $this->getEditActionLabel($definition),
                'url' => $this->resourceUrl($request, Action::EDIT, $resource, $this->getEditRouteParameters($request), $back),
                'style' => 'primary',
            ];
        }

        if ($this->hasMethod(Action::DESTROY) && $this->canDestroyModel($request, $resource)) {
            $actions[] = [
                'label' => $this->getDestroyActionLabel($definition),
                'url' => $this->resourceUrl($request, Action::DESTROY, $resource, $this->getDestroyRouteParameters($request), $back),
                'style' => 'danger',
            ];
        }

        foreach ($this->appPageActions as $appAction) {
            $options = $appAction['options'];
            if (isset($options['condition']) && !call_user_func($options['condition'], $request, $resource)) {
                continue;
            }

            $url = call_user_func($appAction['url'], $request, $resource);
            if ($url === null) {
                continue;
            }

            $actions[] = [
                'label' => $appAction['label'],
                'url' => $url,
                'style' => $options['style'] ?? 'secondary',
                'method' => isset($options['method']) ? strtolower($options['method']) : 'get',
                'confirm' => $options['confirm'] ?? null,
            ];
        }

        return $actions;
    }

    /**
     * Add a button of the application above the show page (and above the
     * app pages that show the hub header), after Edit and Delete:
     *
     * $this->addPageAction('Publish', function (Request $request, RESTResource $post) {
     *     return action('PostController@publish', [ $this->idOf($post) ]);
     * }, [ 'method' => 'post', 'style' => 'success', 'confirm' => 'Publish this post?' ]);
     *
     * Options: 'style', 'method', 'confirm' and 'condition', as for addRowAction().
     * @param string $label
     * @param callable $url function (Request, RESTResource): ?string; null hides the button.
     * @param array $options
     * @return $this
     */
    protected function addPageAction(string $label, callable $url, array $options = [])
    {
        $this->appPageActions[] = [ 'label' => $label, 'url' => $url, 'options' => $options ];
        return $this;
    }

    /**
     * Where Cancel goes: the return url the page was opened with, else the
     * resource's show page, else the index.
     * @param Request $request
     * @param RESTResource|null $resource
     * @return string|null
     */
    protected function getCancelUrl(Request $request, ?RESTResource $resource = null): ?string
    {
        return $this->getReturnUrl($request)
            ?? ($resource ? $this->safeShowUrl($request, $resource) : null)
            ?? $this->getIndexUrl($request);
    }

    /**
     * The return url passed in the query string or the form, if it is a
     * path on this site; null otherwise. Never a full url, so it can't send
     * anyone off-site.
     * @param Request $request
     * @return string|null
     */
    protected function getReturnUrl(Request $request): ?string
    {
        $return = $request->input($this->getReturnParameter());
        if (!is_string($return) || $return === '') {
            return null;
        }

        // A relative path only: no scheme, no host, no protocol-relative
        // "//host" and no backslashes (which browsers read as slashes).
        if (strpos($return, '\\') !== false || preg_match('/[\x00-\x1f]/', $return)) {
            return null;
        }

        $parts = parse_url($return);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || strpos($return, '//') === 0) {
            return null;
        }

        return '/' . ltrim($return, '/');
    }

    /**
     * After create or edit: back to the parent's page for a nested
     * controller opened from there, otherwise the saved resource's show
     * page, otherwise the return url or the index.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterSave(Request $request, RESTResource $resource)
    {
        $return = $this->getReturnUrl($request);

        if ($this->parentController && $return !== null) {
            return Redirect::to($return);
        }

        $url = $this->safeShowUrl($request, $resource)
            ?? $return
            ?? $this->getIndexUrl($request);

        if ($url === null && $this->isMethodAllowed($request, Action::EDIT, $resource)) {
            $url = $this->resourceUrl($request, Action::EDIT, $resource, $this->getEditRouteParameters($request));
        }

        return Redirect::to($url ?? '/');
    }

    /**
     * After delete: the return url, unless it is one of the deleted
     * resource's own pages, otherwise the index.
     * @param Request $request
     * @param RESTResource $resource
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function redirectAfterDestroy(Request $request, RESTResource $resource)
    {
        $return = $this->getReturnUrl($request);

        if ($return !== null) {
            try {
                $deleted = parse_url($this->resourceUrl($request, Action::VIEW, $resource, $this->getShowRouteParameters($request)), PHP_URL_PATH);
            } catch (\Exception $e) {
                // No show page to compare with: only the index is safe.
                $deleted = null;
                $return = null;
            }
            $returnPath = parse_url($return, PHP_URL_PATH);
            if ($deleted && ($returnPath === $deleted || strpos($returnPath, rtrim($deleted, '/') . '/') === 0)) {
                $return = null;
            }
        }

        return Redirect::to($return ?? $this->getIndexUrl($request) ?? '/');
    }

    /**
     * Url of this controller's index, or null when it isn't allowed or
     * can't be built from this request.
     * @param Request $request
     * @return string|null
     */
    protected function getIndexUrl(Request $request): ?string
    {
        if (!$this->hasMethod(Action::INDEX) || !$this->canViewIndex($request)) {
            return null;
        }

        try {
            return action($this->getRawControllerAction('index'), $this->getIndexRouteParameters($request));
        } catch (UrlGenerationException $e) {
            return null;
        } catch (\InvalidArgumentException $e) {
            // No route for this action.
            return null;
        }
    }

    /**
     * getShowUrl() without the exception for routes that can't be built.
     * @param Request $request
     * @param RESTResource $resource
     * @return string|null
     */
    protected function safeShowUrl(Request $request, RESTResource $resource): ?string
    {
        try {
            return $this->getShowUrl($request, $resource);
        } catch (UrlGenerationException $e) {
            return null;
        }
    }

    /**
     * Url of one of this controller's actions for a resource.
     * @param Request $request
     * @param string $action
     * @param RESTResource $resource
     * @param array $routeParameters
     * @param array $queryParameters
     * @return string
     */
    protected function resourceUrl(Request $request, string $action, RESTResource $resource, array $routeParameters = [], array $queryParameters = []): string
    {
        return (new ResourceAction(
            $this->getControllerAction($action),
            $this->getActionText($action, $resource->getResourceDefinition()),
            static::getRouteIdParameterName()
        ))
            ->setRouteParameters($routeParameters)
            ->setQueryParameters($queryParameters)
            ->getUrl($resource);
    }

    /**
     * The parent front controller, see setParentController().
     * @return FrontCrudControllerContract|null
     */
    protected function getParentController()
    {
        if (!$this->parentController) {
            return null;
        }

        $className = $this->parentController['controller'];
        if (!isset($this->childControllers[$className])) {
            $this->childControllers[$className] = new $className;
        }
        return $this->childControllers[$className];
    }

    /**
     * A GET request for this controller built from another page's request:
     * same user, session and route parameters, with this controller's id
     * parameter set to $id.
     * @param Request $request
     * @param mixed $id
     * @return Request
     */
    protected function makeSubRequest(Request $request, $id = null): Request
    {
        $route = $request->route() ? clone $request->route() : null;
        if ($route && $id !== null) {
            $route->setParameter(static::getRouteIdParameterName(), $id);
        }

        $subRequest = Request::create($request->getPathInfo(), 'GET');
        $subRequest->setUserResolver($request->getUserResolver());
        $subRequest->setRouteResolver(function () use ($route) {
            return $route;
        });
        if ($request->hasSession()) {
            $subRequest->setLaravelSession($request->session());
        }

        return $subRequest;
    }

    /**
     * @param Request $request
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function canViewIndex(Request $request)
    {
        return $this->isMethodAllowed($request, Action::INDEX);
    }

    /**
     * @param Request $request
     * @param $model
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function canViewModel(Request $request, $model)
    {
        return $this->isMethodAllowed($request, Action::VIEW, $model);
    }

    /**
     * @param Request $request
     * @param $model
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function canEditModel(Request $request, $model)
    {
        return $this->isMethodAllowed($request, Action::EDIT, $model);
    }

    /**
     * @param Request $request
     * @param $model
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function canDestroyModel(Request $request, $model)
    {
        return $this->isMethodAllowed($request, Action::DESTROY, $model);
    }

    /**
     * @param Request $request
     * @param ResourceDefinition $resourceDefinition
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function canCreateModel(Request $request, ResourceDefinition $resourceDefinition)
    {
        return $this->isMethodAllowed($request, Action::CREATE, $resourceDefinition->getEntityClassName());
    }

    /**
     * @param Request $request
     * @param $action
     * @param null $model
     * @return bool
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function isMethodAllowed(Request $request, $action, $model = null)
    {
        if ($model instanceof RESTResource) {
            // $source contains the original model that this resource was based on.
            // It's a bit hacky to use, but it allows us to do these late checks.
            $source = $model->getSource();
        } elseif (is_string($model)) {
            $source = $model;
        } else {
            $source = $this->getResourceDefinition()->getEntityClassName();
        }

        $parameters = [
            $source
        ];

        // Merge with authorize parameters
        $externalParameters = $this->getAuthorizeParameters($request, $action);
        if ($externalParameters) {
            $parameters = array_merge($parameters, $externalParameters);
        }

        $user = $request->user();
        if (!$user) {
            return false;
        }

        return $user->can($action, $parameters);
    }

    /**
     * @param Request $request
     * @param $action
     * @return array
     */
    protected function getAuthorizeParameters(Request $request, $action)
    {
        return null;
    }

    /**
     * @param $action
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getActionText($action, ResourceDefinition $resourceDefinition)
    {
        return $this->traitGetActionText($action, $resourceDefinition);
    }

    /**
     * @param $action
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function traitGetActionText($action, ResourceDefinition $resourceDefinition)
    {
        switch ($action) {
            case Action::CREATE:
                return $this->getCreateActionLabel($resourceDefinition);

            case Action::EDIT:
                return $this->getEditActionLabel($resourceDefinition);

            case Action::VIEW:
                return $this->getViewActionLabel($resourceDefinition);

            case Action::DESTROY:
                return $this->getDestroyActionLabel($resourceDefinition);
        }

        return ucfirst(strtolower($action));
    }

    /**
     * @return string
     */
    protected function getReturnParameter()
    {
        return 'return';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getCreateActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.create_entity', [ 'entity' => $this->entityLabelOf($resourceDefinition) ]);
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getEditActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.edit');
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getViewActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.view');
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getDestroyActionLabel(ResourceDefinition $resourceDefinition)
    {
        return __('charonfrontend::crud.delete');
    }
}
