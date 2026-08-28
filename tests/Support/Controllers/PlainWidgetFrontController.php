<?php

namespace Tests\Support\Controllers;

/**
 * Widget admin controller that does not map a child controller for
 * Category: related categories can be labelled but not linked.
 */
class PlainWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        $this->setLayout('layouts.test');
    }
}
