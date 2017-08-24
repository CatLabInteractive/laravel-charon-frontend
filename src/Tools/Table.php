<?php

namespace CatLab\CharonFrontend\Tools;

use CatLab\Charon\Collections\ResourceCollection;
use CatLab\Charon\Interfaces\Context;
use CatLab\Charon\Interfaces\ResourceDefinition;
use Illuminate\Support\HtmlString;

/**
 * Class Table
 * @package CatLab\CharonFrontend
 */
class Table
{
    /**
     * @var ResourceCollection
     */
    private $resourceCollection;

    /**
     * @var ResourceDefinition
     */
    private $definition;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $actions;

    /**
     * Table constructor.
     * @param ResourceCollection $collection
     * @param ResourceDefinition $definition
     * @param Context $context
     */
    public function __construct(
        ResourceCollection $collection,
        ResourceDefinition $definition,
        Context $context
    ) {
        $this->resourceCollection = $collection;
        $this->definition = $definition;
        $this->context = $context;
        $this->actions = [];
    }

    /**
     * @return HtmlString
     */
    public function render()
    {
        $columns = [];

        $firstItem = $this->resourceCollection->first();
        if (!$firstItem) {
            return '<p>No content.</p>';
        }

        $columns = array_keys($firstItem->toArray());

        $resources = [];
        foreach ($this->resourceCollection as $v) {
            $resources[] = $v;
        }

        return new HtmlString(view('charonfrontend::table.table', [
            'columns' => $columns,
            'resources' => $resources,
            'actions' => $this->actions
        ])->__toString());
    }

    /**
     * @param $action
     * @param $parameters
     * @param $label
     */
    public function action($action, $parameters, $label)
    {
        $this->actions[] = [
            'action' => $action,
            'parameters' => $parameters,
            'label' => $label
        ];
    }
}