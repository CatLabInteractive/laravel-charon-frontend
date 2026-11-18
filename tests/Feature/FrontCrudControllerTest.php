<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Smoke tests for FrontCrudController: table generation (the "index" admin
 * action), form generation (the "create"/"edit" admin actions) and the
 * delete-confirmation page produce output without throwing, driven through
 * a minimal in-test resource (Tests\Support\Definitions\WidgetDefinition /
 * Tests\Support\Models\Widget) wired the way an Eukles Admin\* controller
 * would wire a real one.
 *
 * This is explicitly scoped as a smoke test, not exhaustive coverage of
 * FrontCrudController.
 *
 * Task 14: crud/form.blade.php, form-fields.blade.php, field.blade.php and
 * destroy.blade.php were rewritten to plain Blade/HTML (no more
 * laravelcollective/html Form::/Html:: facades - see
 * CatLab\CharonFrontend\Support\FormValue and task-14-report.md). These
 * tests now render the *real* views end to end (WidgetFrontController no
 * longer overrides createFormView() to return JSON) and assert on the
 * rendered HTML fidelity the brief called out: a text-ish field, a select
 * with a selected option, a checkbox checked-state, a textarea value, and
 * the form tag's action/method/spoofing.
 */
class FrontCrudControllerTest extends TestCase
{
    public function testIndexRendersTableWithoutThrowing()
    {
        $this->actingAsAuthorizedAdmin();
        $this->seedWidget('Alpha');
        $this->seedWidget('Beta');

        $response = $this->get('/admin/widgets');

        $response->assertStatus(200);
        $response->assertSee('Alpha');
        $response->assertSee('Beta');
    }

    public function testIndexRendersEmptyTableWithoutThrowing()
    {
        $this->actingAsAuthorizedAdmin();

        $response = $this->get('/admin/widgets');

        $response->assertStatus(200);
        $response->assertSee('No data set.');
    }

    /**
     * Create form, no resource: every field renders through its widget at
     * its empty default, and the form posts back to the collection URL with
     * a "post" method-spoof field.
     */
    public function testCreateFormRendersRealHtmlFormWithoutOldValues()
    {
        $response = $this->get('/admin/widgets/create');

        $response->assertStatus(200);

        // Form tag: action/method/CSRF/method-spoof
        $response->assertSee('<form method="POST" action="', false);
        $this->assertStringContainsString('/admin/widgets', $response->getContent());
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="_method" value="post"', false);
        $response->assertDontSee('multipart/form-data', false);

        // "name": string -> TextWidget
        $response->assertSee('<input type="text" id="field-name" name="fields[name]" value="" class="form-control">', false);

        // "status": enum -> SelectWidget, nothing selected
        $response->assertSee('<select class="form-control" id="field-status" name="fields[status]">', false);
        $response->assertSee('<option value="draft">draft</option>', false);
        $response->assertSee('<option value="published">published</option>', false);

        // "active": boolean -> CheckboxWidget, unchecked, with a hidden 0
        $response->assertSee('<input type="hidden" name="fields[active]" value="0">', false);
        $response->assertSee('<input type="checkbox" class="form-check-input" id="field-active" name="fields[active]" value="1">', false);

        // "description": html -> rich TextareaWidget, empty
        $response->assertSee('name="fields[description]"', false);
        $response->assertSee('class="form-control html-richtext-input"', false);

        // "category": linkable relationship -> LinkableWidget
        $response->assertSee('<select class="form-control" id="field-category" name="fields[category][id]">', false);
    }

    /**
     * Edit form, with a persisted resource: every field repopulates from
     * the resource's current value, and the method-spoof field carries "put".
     */
    public function testEditFormRepopulatesFieldsFromResource()
    {
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Widget One', [
            'status' => 'published',
            'active' => true,
            'description' => 'Rich text body',
            'category_id' => $category->id,
        ]);

        $response = $this->get('/admin/widgets/' . $widget->id . '/edit');

        $response->assertStatus(200);

        $response->assertSee('name="_method" value="put"', false);
        $response->assertSee('<input type="text" id="field-name" name="fields[name]" value="Widget One" class="form-control">', false);
        $response->assertSee('<option value="published" selected>published</option>', false);
        $response->assertSee('<option value="draft">draft</option>', false);
        $response->assertSee('<input type="checkbox" class="form-check-input" id="field-active" name="fields[active]" value="1" checked>', false);
        $response->assertSee('Rich text body', false);
        $response->assertSee('<option value="' . $category->id . '" selected>Tools</option>', false);
    }

    /**
     * destroy.blade.php (crud/destroy.blade.php, rendered by confirmDelete())
     * had the exact same laravelcollective 500 as the create/edit form -
     * found after the original brief was written (see task-14-report.md).
     * Covering it here so a regression on this blade fails the suite.
     */
    public function testDeleteConfirmationFormRendersWithoutThrowing()
    {
        $widget = $this->seedWidget('Widget To Delete');

        $response = $this->get('/admin/widgets/' . $widget->id . '/delete');

        $response->assertStatus(200);
        $response->assertSee('Are you sure you want to remove this?');
        $response->assertSee('<form method="POST" action="', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('<input type="submit" value="Yes" class="btn btn-danger">', false);
    }
}
