<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * A single-line text input. Array fields get one input per value plus an
 * empty one to add a value.
 */
class TextWidget extends BaseWidget
{
    protected $view = 'charonfrontend::widgets.text';

    /**
     * @var string
     */
    protected $type = 'text';

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * What an empty input becomes.
     * @var string|null
     */
    protected $emptyValue = null;

    /**
     * @param array $attributes Extra attributes for the input.
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    protected function getViewData(FormField $field): array
    {
        if ($field->isMultiple()) {
            $values = $field->old('', $field->getValue());
            $values = array_values(array_filter((array) $values, function ($v) {
                return $v !== null && $v !== '';
            }));
            $values[] = '';
        } else {
            $values = [ $field->old('', $field->getValue()) ];
        }

        return [
            'values' => $values,
            'type' => $this->type,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * A single-line input would drop line breaks, and saving the form would
     * silently flatten the value. A value that has them gets a textarea.
     */
    public function render(FormField $field): string
    {
        $data = $this->getViewData($field);

        $view = $this->view;
        if ($view === 'charonfrontend::widgets.text' && $this->hasLineBreaks($data['values'])) {
            $view = 'charonfrontend::widgets.textarea';
            $data['attributes'] += [ 'rows' => 5 ];
        }

        return view($view, array_merge($data, [
            'field' => $field,
            'widget' => $this,
        ]))->render();
    }

    /**
     * @param array $values
     * @return bool
     */
    protected function hasLineBreaks(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && strpbrk($value, "\r\n") !== false) {
                return true;
            }
        }
        return false;
    }

    public function read(Request $request, FormField $field)
    {
        $value = $this->input($request, $field);

        if ($field->isMultiple()) {
            $out = [];
            foreach ((array) $value as $v) {
                if ($v !== null && $v !== '') {
                    $out[] = $v;
                }
            }
            return $out;
        }

        $value = $this->nullIfEmpty(is_array($value) ? null : $value);
        return $value ?? $this->emptyValue;
    }
}
