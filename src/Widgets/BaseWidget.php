<?php

namespace CatLab\CharonFrontend\Widgets;

use Illuminate\Http\Request;

/**
 * A widget rendered by a blade view, which gets $field (FormField) and
 * $widget.
 */
abstract class BaseWidget implements Widget
{
    /**
     * @var string
     */
    protected $view;

    public function render(FormField $field): string
    {
        return view($this->view, array_merge($this->getViewData($field), [
            'field' => $field,
            'widget' => $this,
        ]))->render();
    }

    /**
     * Extra data for the view.
     * @param FormField $field
     * @return array
     */
    protected function getViewData(FormField $field): array
    {
        return [];
    }

    public function isMultipart(): bool
    {
        return false;
    }

    /**
     * @param string $view
     * @return $this
     */
    public function setView(string $view): self
    {
        $this->view = $view;
        return $this;
    }

    /**
     * @param Request $request
     * @param FormField $field
     * @param string $path
     * @return mixed
     */
    protected function input(Request $request, FormField $field, string $path = '')
    {
        return $request->input($field->getDotName($path));
    }

    /**
     * Empty strings mean "no value".
     * @param mixed $value
     * @return mixed
     */
    protected function nullIfEmpty($value)
    {
        return ($value === '' || $value === null) ? null : $value;
    }
}
