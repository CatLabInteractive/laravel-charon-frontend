<?php

namespace Tests\Support\Controllers;

use CatLab\Charon\Models\RESTResource;
use Illuminate\Http\Request;

/**
 * An editor the way catlab-events edits a page translation: the parent
 * category's fields in the side column, a folded group, tabs above the
 * form and a second save button.
 */
class EditorWidgetFrontController extends CategoryWidgetFrontController
{
    public function __construct()
    {
        parent::__construct();

        $this->editParentOnForm('Category', [ 'aside' => true, 'description' => 'Shared by all widgets.' ]);
        $this->addFieldGroup('More', [ 'description' ], [ 'collapsed' => true ]);
        $this->addSaveButton('Save and view', 'view');
    }

    protected function getFormTabs(Request $request, string $action, ?RESTResource $resource = null): array
    {
        return [
            [ 'label' => 'This one', 'url' => '/this', 'active' => true, 'badge' => 'draft' ],
            [ 'label' => '+ Another', 'url' => '/another', 'active' => false, 'muted' => true ],
        ];
    }

    protected function redirectAfterSave(Request $request, RESTResource $resource)
    {
        if ($this->getPressedSaveButton($request) === 'view') {
            return redirect('/viewed');
        }

        return parent::redirectAfterSave($request, $resource);
    }
}
