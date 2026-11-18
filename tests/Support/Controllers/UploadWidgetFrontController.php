<?php

namespace Tests\Support\Controllers;

use CatLab\CharonFrontend\Widgets\FileWidget;
use Tests\Support\Definitions\CategoryDefinition;
use Tests\Support\FakeUploadHandler;

/**
 * The category relationship as a file field, the way an application
 * registers its asset definition.
 */
class UploadWidgetFrontController extends WidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->widgets()->forRelationship(CategoryDefinition::class, new FileWidget(new FakeUploadHandler()));
    }
}
