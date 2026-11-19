<?php

namespace CatLab\CharonFrontend\Contracts;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use CatLab\Charon\Models\RESTResource;
use CatLab\Laravel\Table\Table;
use Illuminate\Http\Request;

/**
 * Class FrontCrudControllerContract
 * @package CatLab\CharonFrontend\Contracts
 */
interface FrontCrudControllerContract
{
    /**
     * @param Request $request
     * @param ResourceCollection $collection
     * @param ResourceDefinition $resourceDefinition
     * @param Context $context
     * @return Table
     */
    public function getTableForResourceCollection (
        Request $request,
        ResourceCollection $collection,
        ResourceDefinition $resourceDefinition,
        Context $context
    ): Table;

    /**
     * Url of the show page for a resource of this controller, or null when
     * it cannot be shown.
     * @param Request $request
     * @param RESTResource $resource
     * @return string|null
     */
    public function getShowUrl(Request $request, RESTResource $resource): ?string;

    /**
     * This controller's resources as options for a relationship in another
     * controller's form, label by identifier.
     * @param Request $request
     * @return string[]
     */
    public function getLinkableOptions(Request $request): array;

    /**
     * Breadcrumbs that lead to one of this controller's resources (by id),
     * or to its index without an id; used by the pages of a child
     * controller that setParentController() nests under this one.
     * @param Request $request
     * @param mixed $id
     * @return array [ [ 'label' => string, 'url' => string|null ] ]
     */
    public function getBreadcrumbTrail(Request $request, $id = null): array;

    /**
     * The edit form fields of one of this controller's resources for a
     * child's edit form (editParentOnForm()), inputs under parent[...].
     * @param Request $request
     * @param mixed $id
     * @return array|null
     */
    public function getFormFieldsForChildForm(Request $request, $id): ?array;

    /**
     * Save one of this controller's resources from a child's form.
     * @param Request $request
     * @param mixed $id
     * @return \Symfony\Component\HttpFoundation\Response|null Null when saved.
     */
    public function saveFromChildForm(Request $request, $id);
}
