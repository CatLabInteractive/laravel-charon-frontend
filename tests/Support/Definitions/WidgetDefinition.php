<?php

namespace Tests\Support\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Support\Models\Widget;

/**
 * Minimal ResourceDefinition, following the same shape as charon-laravel's
 * TagMetadataDefinition test fixture: an identifier plus a single writeable
 * scalar field. That's enough surface area to exercise field filtering in
 * FrontCrudController::formView() and column rendering in the table view.
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
        ;
    }
}
