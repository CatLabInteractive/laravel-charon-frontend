<?php

namespace Tests\Support\Controllers;

use Illuminate\Http\Request;

/**
 * A category api whose index only lists "our" categories, the way an
 * application scopes its index to the active organisation.
 */
class ScopedCategoryApiController extends CategoryApiController
{
    protected function getIndexQuery(Request $request)
    {
        return parent::getIndexQuery($request)->where('name', 'not like', 'Other%');
    }
}
