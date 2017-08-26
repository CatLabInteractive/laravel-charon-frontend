<?php

namespace CatLab\CharonFrontend\Contracts;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
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
}