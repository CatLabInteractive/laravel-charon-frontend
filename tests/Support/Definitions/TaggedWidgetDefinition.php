<?php

namespace Tests\Support\Definitions;

/**
 * WidgetDefinition plus a relationship to many, for widget unit tests only
 * (the fixture model has no such relation).
 */
class TaggedWidgetDefinition extends WidgetDefinition
{
    public function __construct()
    {
        parent::__construct();

        $this->relationship('tags', CategoryDefinition::class)
            ->many()
            ->linkable()
            ->visible();
    }
}
