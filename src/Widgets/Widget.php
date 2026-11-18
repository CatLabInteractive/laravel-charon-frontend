<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * How one field of a resource definition is edited in a form: what it
 * renders, and how the submitted input turns back into Charon input.
 *
 * The front controller picks a widget per field through its WidgetRegistry
 * (see FrontCrudController::widgets()).
 */
interface Widget
{
    /**
     * @param FormField $field
     * @return string Html.
     */
    public function render(FormField $field): string;

    /**
     * The Charon input for this field, from the submitted form: a scalar,
     * an array of scalars, [ 'id' => .. ] for a linked resource,
     * [ 'items' => [ [ 'id' => .. ], .. ] ] for linked resources, or null.
     *
     * @param Request $request
     * @param FormField $field
     * @return mixed
     */
    public function read(Request $request, FormField $field);

    /**
     * Whether the form needs enctype="multipart/form-data".
     * @return bool
     */
    public function isMultipart(): bool;
}
