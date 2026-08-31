<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Models\RESTResource;

/**
 * Widget admin controller that names some of the resources appearing in its
 * tables through getRelatedResourceLabel(), and declines on the rest by
 * returning null -- which leaves them to laravel-table's default
 * name-like-field heuristic.
 */
class LabelledWidgetFrontController extends WidgetFrontController
{
    protected function getRelatedResourceLabel(RESTResource $related): ?string
    {
        $name = $related->toArray()['name'] ?? null;

        return $name === 'Tools' ? 'Toolbox (' . $name . ')' : null;
    }
}
