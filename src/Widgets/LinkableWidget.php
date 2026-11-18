<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * Links existing resources: a select for a relationship to one. For a
 * relationship to many, a short list of checkboxes, or for longer lists a
 * searchable picker that shows the linked items as removable chips (a plain
 * multiple select without JavaScript). The options come from
 * FrontCrudController::resolveLinkableOptions().
 */
class LinkableWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.linkable';

    /**
     * @var int
     */
    protected $checkboxLimit;

    /**
     * @param int $checkboxLimit Up to this many options, a relationship to many
     *                           renders as checkboxes; above it, as a picker.
     */
    public function __construct(int $checkboxLimit = 6)
    {
        $this->checkboxLimit = $checkboxLimit;
    }

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
            'picker' => $field->isMultiple() && count($field->getOptions()) > $this->checkboxLimit,
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
