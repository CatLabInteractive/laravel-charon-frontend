<?php

namespace CatLab\CharonFrontend\Models\Table;

use CatLab\Charon\Models\RESTResource;
use CatLab\Laravel\Table\Models\ModelAction;

/**
 * Class ResourceAction
 * @package CatLab\CharonFrontend\Models\Table
 */
class ResourceAction extends ModelAction
{
    /**
     * Get the identifier of a model.
     * @param $model
     * @return mixed
     */
    public function getIdFromModel($model)
    {
        if (! ($model instanceof RESTResource)) {
            return parent::getIdFromModel($model);
        }

        return ($model->getIdentifiers()->getValues())[0]->getValue();
    }
}