<?php

namespace CatLab\CharonFrontend\Controllers;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Enums\Action;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Laravel\Controllers\ResourceController;
use CatLab\Charon\Models\Context;
use CatLab\Charon\Models\ResourceResponse;
use CatLab\Charon\Models\RESTResource;
use CatLab\Charon\Models\Values\Base\RelationshipValue;
use CatLab\CharonFrontend\Exceptions\UnexpectedResponse;
use CatLab\CharonFrontend\Exceptions\UnresolvedMethodException;
use CatLab\CharonFrontend\Tools\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Redirect;
use Session;

/**
 * Class CrudController
 * @package CatLab\CharonFrontend\Controllers
 */
trait CrudController
{
    /**
     * This method should return an instance of the corresponding api controller.
     * @return ResourceController
     */
    abstract function createApiController();

    /**
     * @var mixed
     */
    private $apiController;

    protected $routeMap = [
        Action::INDEX => 'index',
        Action::CREATE => 'store',
        Action::VIEW => 'view',
        Action::EDIT => 'edit',
        Action::DESTROY => 'destroy'
    ];

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Response
     * @throws UnexpectedResponse
     */
    public function index(Request $request)
    {
        $response = $this->dispatchToApi(Action::INDEX, [ $request ]);

        if (!($response instanceof ResourceResponse)) {
            throw UnexpectedResponse::createNoResourceCollection($response);
        }

        $resourceCollection = $response->getResource();
        if (!($resourceCollection instanceof ResourceCollection)) {
            throw UnexpectedResponse::createNoResourceCollection($response);
        }

        $table = new Table(
            $resourceCollection,
            $this->getResourceDefinition(),
            $response->getContext()
        );

        if ($this->hasMethod(Action::VIEW)) {
            $table->action($this->getControllerAction('show'), 'Show');
        }

        if ($this->hasMethod(Action::EDIT)) {
            $table->action($this->getControllerAction('edit'), 'Edit');
        }

        if ($this->hasMethod(Action::DESTROY)) {
            $table->action($this->getControllerAction('confirmDelete'), 'Delete');
        }

        $view = $this->getView('index');

        $actions = [];

        if ($this->hasMethod(Action::CREATE)) {
            $actions[] = $this->getAction('create');
        }

        return view($view, [
            'table' => $table,
            'actions' => $actions
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return $this->formView(Action::CREATE, 'store');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $response = $this->dispatchToApi(Action::CREATE, [ $request ]);

        if (!($response instanceof ResourceResponse)) {
            return $this->handleErrorResponse($response, 'store');
        }

        return $this->afterStore($response->getResource());
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        $response = $this->dispatchToApi(Action::VIEW, [ $request ]);
        $resource = $response->getResource();

        $view = $this->getView(Action::VIEW);

        $context = $response->getContext();

        $data = [
            'resource' => $resource,
            'relationships' => []
        ];

        // Look for relationships and build tables
        foreach ($resource->getProperties()->getRelationships()->getValues() as $relationship) {

            /** @var RelationshipValue $relationship */
            $data['relationships'][] = [
                'property' => $relationship,
                'title' => $relationship->getField()->getName(),
                'table' => $this->getTableForResourceCollection(
                    $relationship->getChildren(),
                    $relationship->getField()->getChildResource(),
                    $context
                )
            ];
        }

        return view($view, $data);
    }

    /**
     * Show the edit form
     * @param Request $request
     * @return Response
     */
    public function edit(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, [ $request ]);
        return $this->formView(Action::CREATE, 'update', $resource->getResource());
    }

    /**
     * Update an entity and redirect back to index page
     * @param Request $request
     * @param $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $response = $this->dispatchToApi(Action::EDIT, [ $request ]);

        if (!($response instanceof ResourceResponse)) {
            return $this->handleErrorResponse($response, 'store');
        }

        return $this->afterUpdate($response->getResource());
    }

    public function confirmDelete(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, [ $request ]);
        $resource = $resource->getResource();

        $view = $this->getView(Action::DESTROY);

        return view($view, [
            'resource' => $resource,
            'action' => $this->action('destroy'),
            'back' => $this->action('index')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        $resource = $this->dispatchToApi(Action::VIEW, [ $request ])->getResource();
        $this->dispatchToApi(Action::DESTROY, [ $request ]);

        return $this->afterDestroy($resource);
    }

    /**
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @return Table
     */
    protected function getTableForResourceCollection (
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        Context $context
    ) {
        $table = new Table(
            $collection,
            $resourceDefinition,
            $context
        );

        if ($this->hasMethod(Action::VIEW)) {
            $table->action($this->getControllerAction('show'), 'Show');
        }

        if ($this->hasMethod(Action::EDIT)) {
            $table->action($this->getControllerAction('edit'), 'Edit');
        }

        if ($this->hasMethod(Action::DESTROY)) {
            $table->action($this->getControllerAction('confirmDelete'), 'Delete');
        }

        return $table;
    }

    /**
     * Create and return the corresponding Api Controller
     * @return ResourceController
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
     * @param $action
     * @param array $parameters
     * @return ResourceResponse|Response
     */
    protected function dispatchToApi($action, $parameters = [])
    {
        $method = $this->resolveMethod($action);
        $controller = $this->getApiController();

        $response = call_user_func_array([ $controller, $method ], $parameters);

        return $response;
    }

    /**
     * @return mixed
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
        return action($this->getControllerAction($method), $parameters);
    }

    /**
     * Get the name of the action
     * @param $method
     * @return string
     */
    protected function getControllerAction($method)
    {
        return '\\' . self::class . '@' . $method;
    }

    /**
     * @param $action
     * @param $processmethod
     * @param null $model
     * @return \Illuminate\Http\Response
     */
    protected function formView($action, $processmethod, $model = null)
    {
        $context = new Context($action, []);

        $resourceDefinition = $this->getResourceDefinition();
        $fields = $resourceDefinition->getFields()->getWithAction($context->getAction());

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

        return view($view, [
            'fields' => $fields,
            'action' => $this->action($processmethod),
            'resource' => $model,
            'verb' => $verb
        ]);
    }

    /**
     * Executed after store.
     * @param RESTResource $newResource
     * @return \Illuminate\Http\Response
     */
    protected function afterStore(RESTResource $newResource)
    {
        $entityName = $this->getResourceDefinition()->getEntityName();

        Session::flash('message', 'A new ' . $entityName . ' was born...');
        return Redirect::to(action('\\' . self::class . '@index'));
    }

    /**
     * Executed after store.
     * @param RESTResource $resource
     * @return \Illuminate\Http\Response
     */
    protected function afterUpdate(RESTResource $resource)
    {
        $entityName = $this->getResourceDefinition()->getEntityName();

        Session::flash('message', 'Saved.');
        return Redirect::to(action('\\' . self::class . '@index'));
    }

    /**
     * Executed after destroy.
     * @param RESTResource $resource
     * @return \Illuminate\Http\Response
     */
    protected function afterDestroy(RESTResource $resource)
    {
        $entityName = $this->getResourceDefinition()->getEntityName();

        Session::flash('message', 'Deleted.');
        return Redirect::to(action('\\' . self::class . '@index'));
    }

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
            ->withInput()
            ;
    }
}