<?php

namespace Tests\Support\Policies;

use Tests\Support\Models\AdminUser;
use Tests\Support\Models\Category;

/**
 * A policy that only knows how to answer questions about categories: the
 * category one is created under is a Category, and nothing else. Typed on
 * purpose - that is what a real application's policies look like, and it is
 * what breaks loudly when a controller hands them somebody else's scope.
 */
class CategoryPolicy
{
    public function view(AdminUser $user, Category $category)
    {
        return true;
    }

    public function create(AdminUser $user, Category $parent)
    {
        return true;
    }
}
