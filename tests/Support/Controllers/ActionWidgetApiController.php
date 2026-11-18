<?php

namespace Tests\Support\Controllers;

use Illuminate\Http\Request;
use Tests\Support\Models\Widget;

/**
 * A widget api that refuses to delete the widget called "Locked", and that
 * reports an issue nothing in the form has a field for.
 */
class ActionWidgetApiController extends WidgetApiController
{
    public function destroy(Request $request)
    {
        $widget = Widget::findOrFail($request->route('id'));
        if ($widget->name === 'Locked') {
            return response()->json([
                'error' => [
                    'message' => 'Could not decode resource.',
                    'issues' => [ 'widget' => [ 'A locked widget <b>stays</b>.' ] ],
                ],
            ], 422);
        }

        $widget->delete();

        return response()->json([ 'success' => true ]);
    }

    public function store(Request $request)
    {
        if ($request->input('name') === 'Taken') {
            return response()->json([
                'error' => [
                    'message' => 'Could not decode resource.',
                    'issues' => [
                        'name' => [ 'That name is taken.' ],
                        'active' => [ 'Activate it later.' ],
                        'stock' => [ 'There is no stock yet.' ],
                    ],
                ],
            ], 422);
        }

        return response()->json([ 'error' => [ 'message' => 'Nothing to store here.' ] ], 400);
    }
}
