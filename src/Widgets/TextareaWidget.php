<?php

namespace CatLab\CharonFrontend\Widgets;

/**
 * A textarea. With $rich, it carries the html-richtext-input class that
 * applications hook their editor onto (the default for html() fields).
 */
class TextareaWidget extends TextWidget
{
    protected $view = 'charonfrontend::widgets.textarea';

    /**
     * @param bool $rich
     * @param array $attributes
     */
    public function __construct(bool $rich = false, array $attributes = [])
    {
        $attributes += [ 'rows' => 5 ];
        if ($rich) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' html-richtext-input');

            // Charon's HtmlTransformer only takes strings.
            $this->emptyValue = '';
        }

        parent::__construct($attributes);
    }
}
