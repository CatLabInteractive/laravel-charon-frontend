<?php

namespace Tests\Support\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Support\Models\Widget;

/**
 * ResourceDefinition for the form-fidelity smoke tests (Task 14): one field
 * per crud/field.blade.php branch that needs to be exercised without
 * laravelcollective/html -
 *  - 'name': plain text input (the textarea "else" branch's sibling: a
 *    scalar string field with no allowed values renders as a text-ish
 *    <textarea rows="1">, see field.blade.php)
 *  - 'status': enum -> <select> with a selected option
 *  - 'active': boolean -> checkbox with checked-state
 *  - 'description': html -> rich <textarea> (field.blade.php's 'html' branch)
 */
class WidgetDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Widget::class);

        $this
            ->identifier('id')
                ->int()

            ->field('name')
                ->string()
                ->required()
                ->writeable()
                ->visible(true, true)

            ->field('status')
                ->string()
                ->enum(['draft', 'published'])
                ->writeable()
                ->visible(true, true)

            ->field('active')
                ->bool()
                ->writeable()
                ->visible(true, true)

            ->field('description')
                ->html()
                ->writeable()
                ->visible(true, true)
        ;
    }
}
