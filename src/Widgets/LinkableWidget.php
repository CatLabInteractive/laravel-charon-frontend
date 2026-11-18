<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * Links existing resources: a select for a relationship to one, a list of
 * checkboxes for a relationship to many. The options come from
 * FrontCrudController::resolveLinkableOptions().
 */
class LinkableWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.linkable';

    protected function getViewData(FormField $field): array
    {
        if ($field->isMultiple()) {
            $selected = $field->hasOldInput()
                ? (array) $field->old('ids', [])
                : $field->getValue();
        } else {
            $selected = [ $field->old('id', $field->getValue()) ];
        }

        return [
            'selected' => array_map('strval', array_filter($selected, function ($v) {
                return $v !== null && $v !== '';
            })),
        ];
    }

    public function read(Request $request, FormField $field)
    {
        if ($field->isMultiple()) {
            $items = [];
            foreach ((array) $this->input($request, $field, 'ids') as $id) {
                if ($id !== null && $id !== '') {
                    $items[] = [ 'id' => $id ];
                }
            }
            return [ 'items' => $items ];
        }

        $id = $this->nullIfEmpty($this->input($request, $field, 'id'));
        return $id === null ? null : [ 'id' => $id ];
    }
}
