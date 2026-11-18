<?php

namespace CatLab\CharonFrontend\Models\Table;

use CatLab\Laravel\Table\Models\ModelAction;

/**
 * A row action whose url is computed per resource by the application
 * (FrontCrudController::addRowAction()) instead of from a controller action.
 */
class CallbackAction extends ModelAction
{
    /**
     * @var \Closure
     */
    private $url;

    /**
     * @param string $label
     * @param \Closure $url function ($resource): ?string
     */
    public function __construct($label, \Closure $url)
    {
        parent::__construct(null, $label);
        $this->url = $url;
    }

    /**
     * @param mixed $model
     * @return string
     */
    public function getUrl($model): string
    {
        return (string) call_user_func($this->url, $model);
    }
}
