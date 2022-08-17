<?php

namespace CatLab\CharonFrontend\Controllers;

use Carbon\Carbon;
use CatLab\Base\Helpers\ArrayHelper;
use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\Context as ContextContract;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Models\Properties\Base\Field;
use CatLab\Charon\Models\Properties\IdentifierField;
use CatLab\Charon\Models\Properties\RelationshipField;
use CatLab\Charon\Laravel\Models\ResourceResponse;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\ChildrenValue;
use CatLab\CharonFrontend\Contracts\FrontCrudControllerContract;
use CatLab\CharonFrontend\Exceptions\UnexpectedResponse;
use CatLab\CharonFrontend\Exceptions\UnresolvedMethodException;

use CatLab\CharonFrontend\Models\Table\ResourceAction;
use CatLab\Laravel\Table\Models\CollectionAction;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
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
    public static function getRouteIdParameterName(): string
    {
        return 'id';
    }

    /**
     * @return string
     */
    public static function getApiRouteIdParameterName(): string
    {
        return 'id';
    }

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
     * @var string
     */
    private $layout = 'layouts.app';

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
        $newRequest = $this->transformFormInput($request);
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

            if ($relationship instanceof ChildrenValue) {

                $childResourceDefinition = $relationship->getField()->getChildResource();

                if (!isset($this->childControllerMap[get_class($childResourceDefinition)])) {
                    continue;
                }

                $childController = new $this->childControllerMap[get_class($childResourceDefinition)];
                if (! ($childController instanceof FrontCrudControllerContract)) {
                    abort(500, 'Only controllers implementing FrontCrudControllerContract' .
                        'can be used for expanding relationships'
                    );
                }

                $data['relationships'][] = [
                    'property' => $relationship,
                    'title' => $relationship->getField()->getName(),
                    'table' => $childController->getTableForResourceCollection(
                        $request,
                        $relationship->getChildren(),
                        $relationship->getField()->getChildResource(),
                        $context
                    )
                ];
            }

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
        $request = $this->transformFormInput($request);
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
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param ContextContract $context
     * @return Table
     */
    public function traitGetTableForResourceCollection (
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
        return $this->getApiController()->getResourceDefinition();
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
        return '\\' . self::class . '@' . $action;
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
        $context = new Context($action, []);

        $model = null;
        if ($response || $response instanceof ResourceResponse) {
            $model = $response->getResource();

            /** @var ResourceDefinition $resourceDefinition */
            $resourceDefinition = $response->getResource()->getResourceDefinition();
        } else {
            /** @var ResourceDefinition $resourceDefinition */
            $resourceDefinition = $this->getResourceDefinition();
        }

        $fields = $resourceDefinition
            ->getFields()
            ->filter(
                function(Field $field) {
                    return !$field instanceof IdentifierField
                        && !$field instanceof RelationshipField;
                }
            )
            ->getWithAction($context->getAction());

        $relationships = $resourceDefinition
            ->getFields()
            ->filter(
                function(Field $field) use ($context) {
                    if ($field instanceof RelationshipField) {
                        if (
                            $field->canLinkExistingEntities($context)
                        ) {
                            return true;
                        }
                    }
                    return false;
                }
            )
            ->getWithAction($context->getAction());

        $view = $this->getView('form');

        switch ($processmethod) {

            case 'update':
                $verb = 'put';
                break;

            case 'store':
            default:
                $verb = 'post';
                break;
        }

        // prepare linksables
        $linkables = [];
        foreach ($relationships as $relationship) {
            $linkables[] = [
                'field' => $relationship,
                'values' => $this->resolveValues($relationship)
            ];
        }

        return $this->createFormView($action, $view, [
            'fields' => $fields,
            'linkables' => $linkables,
            'action' => $this->action($processmethod),
            'resource' => $model,
            'verb' => $verb,
            'layout' => $this->layout
        ]);
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
     * Translate form input into Charon post input.
     * @param Request $request
     * @return Request
     */
    protected function transformFormInput(Request $request)
    {
        $out = [];

        $fields = $request->input('fields');
        if (isset($fields)) {
            foreach ($fields as $k => $v) {
                if (is_array($v)) {
                    if ($v['multiple']) {
                        $out[$k] = [];
                        if (isset($v['input']) && is_array($v['input'])) {
                            foreach ($v['input'] as $input) {
                                $fieldValue = $this->transformInputField($v['type'], $input);
                                if ($fieldValue !== null) {
                                    $out[$k][] = $fieldValue;
                                }
                            }
                        }
                    } elseif (isset($v['input']) && is_array($v['input']) && key_exists(0, $v['input'])) {
                        $out[$k] = $this->transformInputField($v['type'], $v['input'][0]);
                    } else {
                        $out[$k] = $this->transformInputField($v['type'], [ 'value' => null ]);
                    }
                }
            }
        }

        // Relationships
        $linkables = $request->input('linkable');
        $linkableFields = $request->input('linkableFields');

        if (isset($linkableFields)) {
            $linkableFields = explode(',', $linkableFields);

            foreach ($linkableFields as $k) {

                if (isset($linkables[$k])) {
                    $v = $linkables[$k];
                } else {
                    // if not provided, it must be a multiselect
                    $v = null;
                    $out[$k] = [
                        'items' => []
                    ];
                }

                if ($v) {
                    if (!ArrayHelper::isAssociative($v)) {
                        $out[$k] = [
                            'items' => []
                        ];

                        foreach ($v as $vv) {
                            if ($this->isLinkableValid($vv)) {
                                $out[$k]['items'][] = $vv;
                            } else {
                                $out[$k] = null;
                                break;
                            }
                        }
                    } elseif ($this->isLinkableValid($v)) {
                        $out[$k] = $v;
                    } else {
                        $out[$k] = null;
                    }
                }
            }
        }

        $newRequest = $request->duplicate();
        $newRequest->replace($out);

        //$request->request = $out;
        return $newRequest;
    }


    /**
     * @param $identifiers
     * @return bool
     */
    protected function isLinkableValid($identifiers)
    {
        if (!is_array($identifiers)) {
            return false;
        }

        foreach ($identifiers as $identifier) {
            if (!empty($identifier)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $type
     * @param array $v
     * @return mixed|null|string
     */
    protected function transformInputField($type, array $v)
    {
        switch ($type) {
            case 'dateTime':

                if (isset($v['date']) && isset($v['time'])) {
                    $dateTime = Carbon::parse($v['date'] . ' ' . $v['time']);
                    return $dateTime->format(DATE_RFC822);
                }

                break;

            case 'boolean':
                return isset($v['value']) && $v['value'];

            default:
                if (isset($v['value'])) {
                    return $v['value'];
                }
                break;
        }

        return null;
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
    protected function redirectBackToIndex(Request $request, RESTResource $resource = null)
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
            return Redirect::to(action('\\' . self::class . '@index', $parameters));
        } elseif ($resource && $this->isMethodAllowed($request, Action::VIEW, $resource)) {
            $parameters = [ static::getRouteIdParameterName() => $resource->getIdentifiers()->getValues()[0]->getValue() ];
            return Redirect::to(action('\\' . self::class . '@show', $parameters));
        } elseif ($resource && $this->isMethodAllowed($request, Action::EDIT, $resource)) {
            $parameters = [ static::getRouteIdParameterName() => $resource->getIdentifiers()->getValues()[0]->getValue() ];
            return Redirect::to(action('\\' . self::class . '@edit', $parameters));
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
                return 'Create ' . $resourceDefinition->getEntityName();

            case Action::EDIT:
                return 'Edit';

            case Action::VIEW:
                return 'View';

            case Action::DESTROY:
                return 'Delete';
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
}
