<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * A select over the field's allowed values (enum()).
 */
class SelectWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.select';

    protected function getViewData(FormField $field): array
    {
        return [
            'selected' => (string) $field->old('', $field->getValue()),
        ];
    }

    public function read(Request $request, FormField $field)
    {
        return $this->nullIfEmpty($this->input($request, $field));
    }
}
