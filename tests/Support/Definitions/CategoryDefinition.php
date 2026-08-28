<?php

namespace Tests\Support\Definitions;

use CatLab\Charon\Models\ResourceDefinition;
use Tests\Support\Models\Category;

class CategoryDefinition extends ResourceDefinition
{
    public function __construct()
    {
        parent::__construct(Category::class);

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
