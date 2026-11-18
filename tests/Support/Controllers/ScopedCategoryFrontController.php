<?php

namespace Tests\Support\Controllers;

class ScopedCategoryFrontController extends CategoryFrontController
{
    public function createApiController()
    {
        return new ScopedCategoryApiController();
    }
}
