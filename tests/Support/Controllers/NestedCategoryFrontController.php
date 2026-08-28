<?php

namespace Tests\Support\Controllers;

use Illuminate\Http\Request;

/**
 * Category admin controller mounted under /admin/shops/{shop}/categories:
 * its show url needs a {shop} parameter the widget pages don't have, the
 * way Eukles' SegmentController needs {segmentschema}.
 */
class NestedCategoryFrontController extends CategoryFrontController
{
    protected function getRouteParameters(Request $request, $method)
    {
        return [
            'shop' => $request->route('shop')
        ];
    }
}
