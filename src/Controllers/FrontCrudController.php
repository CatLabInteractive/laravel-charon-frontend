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

use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\CharonFrontend\Widgets\FormField;
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
                'layout' => $this->layout
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
        // set the return parameter
        $request->session()->put('frontcrud_index_redirect', $request->input($this->getReturnParameter()));

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
        $newRequest = $this->transformFormInput($request, Action::CREATE);
        $response = $this->dispatchToApi(Action::CREATE, $newRequest);

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

        $data = [
            'layout' => $this->layout,
            'resource' => $resource,
            'relationships' => []
        ];

        if (! ($resource instanceof RESTResource)) {
            abort(404, 'Only Resources can be shown.');
        }

        // Look for relationships and build tables
        foreach ($resource->getProperties()->getRelationships()->getValues() as $relationship) {

            $childResourceDefinition = $relationship->getField()->getChildResourceDefinition();

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
                    'title' => $relationship->getField()->getName(),
                    'cell' => $table->makeCell($relationship)
                ];
                continue;
            }

            if (!($relationship instanceof ChildrenValue)) {
                continue;
            }

            $childController = $this->getChildController($childResourceDefinition);
            if (!$childController) {
                continue;
            }

            $table = $childController->getTableForResourceCollection(
                $request,
                $relationship->getChildren(),
                $childResourceDefinition,
                $context
            );

            $data['relationships'][] = [
                'multiple' => true,
                'property' => $relationship,
                'title' => $relationship->getField()->getName(),
                'table' => $table
            ];

        }

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
        // set the return parameter
        $request->session()->put('frontcrud_index_redirect', $request->input($this->getReturnParameter()));

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
        $request = $this->transformFormInput($request, Action::EDIT);
        $response = $this->dispatchToApi(Action::EDIT, $request);

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
        // set the return parameter
        $request->session()->put('frontcrud_index_redirect', $request->input($this->getReturnParameter()));

        $resource = $this->dispatchToApi(Action::VIEW, $request);
        $resource = $resource->getResource();

        $view = $this->getView(Action::DESTROY);
        return view($view, [
            'resource' => $resource,
            'action' => $this->action('destroy'),
            'back' => $this->action('index'),
            'layout' => $this->layout
        ]);
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
        $this->dispatchToApi(Action::DESTROY, $request);

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

        return $table;
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
            $table->modelAction(
                (new ResourceAction(
                    $this->getControllerAction(Action::VIEW),
                    $this->getActionText(Action::VIEW, $resourceDefinition),
                    static::getRouteIdParameterName()
                ))
                    ->setRouteParameters($this->getShowRouteParameters($request))
                    ->setQueryParameters($this->getShowQueryParameters($request))
                    ->setCondition(function($model) use ($request) {
                        return $this->canViewModel($request, $model);
                    })
            );
        }

        if ($this->hasMethod(Action::EDIT)) {
            $table->modelAction(
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
            );
        }

        if ($this->hasMethod(Action::DESTROY)) {
            $table->modelAction(
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
            );
        }

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
        $parameters[$this->getReturnParameter()] = $request->path();

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
        $parameters[$this->getReturnParameter()] = $request->path();

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
        $parameters[$this->getReturnParameter()] = $request->path();

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
            $formField->setOptions($this->resolveFieldOptions($request, $formField, $widget));

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

        return $this->createFormView($action, $this->getView('form'), [
            'formFields' => $formFields,
            'multipart' => $multipart,
            'action' => $this->action($processmethod),
            'resource' => $resource,
            'verb' => $verb,
            'method' => $method,
            'layout' => $this->layout
        ]);
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
            $out[] = new FormField($field, $resource);
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
        $entityName = $this->getResourceDefinition()->getEntityName();

        Session::flash('message', 'A new ' . $entityName . ' was born...');

        return $this->redirectBackToIndex($request, $newResource);
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
        $entityName = $this->getResourceDefinition()->getEntityName();
        Session::flash('message', 'Saved.');

        return $this->redirectBackToIndex($request, $resource);
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
        $entityName = $this->getResourceDefinition()->getEntityName();
        Session::flash('message', 'Deleted.');

        return $this->redirectBackToIndex($request);
    }

    /**
     * @param JsonResponse $response
     * @param $redirectMethod
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleErrorResponse(JsonResponse $response, $redirectMethod)
    {
        $data = $response->getData(true);

        $message = $data['error']['message'];

        if (isset($data['error']['issues'])) {
            // Validation errors!
            foreach ($data['error']['issues'] as $field => $errors) {
                foreach ($errors as $error) {
                    $message .= '<br>' . $error;
                }
            }
        }

        return redirect()->back()
            ->with('message', new HtmlString($message))
            ->withInput();
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
     * Called after create or destroy;
     * redirect back to the index page.
     * @param Request $request
     * @param RESTResource|null $resource
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \CatLab\Charon\Exceptions\InvalidResourceDefinition
     * @throws \CatLab\Charon\Exceptions\ResourceException
     */
    protected function redirectBackToIndex(Request $request, ?RESTResource $resource = null)
    {
        // look for a return parameter
        $return = $request->session()->get('frontcrud_index_redirect');
        if ($return) {
            $request->session()->forget('frontcrud_index_redirect', null);

            // replace any resource identifiers that might be generated
            if ($resource) {
                $identifiers = $resource->getProperties()->getIdentifiers()->getValues();
                foreach ($identifiers as $identifier) {
                    // Replace {id} with the actual id of the (new/updated) resource..
                    $return = str_replace(
                        '{' . $identifier->getField()->getDisplayName() . '}',
                        $identifier->getValue(),
                        $return
                    );
                }
            }

            return Redirect::to($return);
        }

        // redirect to default index (if allowed)
        if ($this->canViewIndex($request)) {
            $parameters = $this->getIndexRouteParameters($request);
            return Redirect::to(action('\\' . static::class . '@index', $parameters));
        } elseif ($resource && $this->isMethodAllowed($request, Action::VIEW, $resource)) {
            $parameters = [ static::getRouteIdParameterName() => $resource->getIdentifiers()->getValues()[0]->getValue() ];
            return Redirect::to(action('\\' . static::class . '@show', $parameters));
        } elseif ($resource && $this->isMethodAllowed($request, Action::EDIT, $resource)) {
            $parameters = [ static::getRouteIdParameterName() => $resource->getIdentifiers()->getValues()[0]->getValue() ];
            return Redirect::to(action('\\' . static::class . '@edit', $parameters));
        } else {
            return Redirect::to('/');
        }
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
        return 'Create ' . $resourceDefinition->getEntityName();
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getEditActionLabel(ResourceDefinition $resourceDefinition)
    {
        return 'Edit';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getViewActionLabel(ResourceDefinition $resourceDefinition)
    {
        return 'View';
    }

    /**
     * @param ResourceDefinition $resourceDefinition
     * @return string
     */
    protected function getDestroyActionLabel(ResourceDefinition $resourceDefinition)
    {
        return 'Delete';
    }
}
