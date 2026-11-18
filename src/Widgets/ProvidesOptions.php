<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * A relationship widget that knows its own options, instead of getting them
 * from the related resource's front controller (see
 * FrontCrudController::resolveLinkableOptions()).
 */
interface ProvidesOptions
{
    /**
     * @param Request $request
     * @param FormField $field
     * @return string[] Label by identifier.
     */
    public function getOptions(Request $request, FormField $field): array;
}
