<?php

namespace Tests\Support\Controllers;

/**
 * A form with field groups: status + active together, and a group whose
 * fields aren't on the form.
 */
class GroupedWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->addFieldGroup('State', [ 'active', 'status', 'not_a_field' ]);
        $this->addFieldGroup('Nothing', [ 'not_a_field_either' ]);
    }
}
