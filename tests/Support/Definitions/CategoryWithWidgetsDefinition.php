<?php

namespace Tests\Support\Definitions;

/**
 * A category that owns its widgets: the show page gets a Widgets tab.
 */
class CategoryWithWidgetsDefinition extends CategoryDefinition
{
    public function __construct()
    {
        parent::__construct();

        $this->relationship('widgets', WidgetDefinition::class)
            ->many()
            ->expanded()
            ->visible();
    }
}
