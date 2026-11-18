<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * A checkbox, with a hidden "0" before it so unchecking is submitted too.
 */
class CheckboxWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.checkbox';

    protected function getViewData(FormField $field): array
    {
        return [
            'checked' => (bool) $field->old('', $field->getValue()),
        ];
    }

    public function read(Request $request, FormField $field)
    {
        return (bool) $this->input($request, $field);
    }
}
