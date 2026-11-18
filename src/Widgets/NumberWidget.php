<?php

namespace CatLab\CharonFrontend\Widgets;

/**
 * <input type="number">, decimals allowed unless the attributes say otherwise.
 */
class NumberWidget extends TextWidget
{
    protected $type = 'number';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes + [ 'step' => 'any' ]);
    }
}
