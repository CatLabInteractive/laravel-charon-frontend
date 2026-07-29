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
     * Create form, no resource: every field renders at its "empty" default
     * (no old input, no repopulation), and the form tag posts back to the
     * collection URL with POST + a "post" method-spoof field (matches the
     * pre-rewrite laravelcollective behaviour: Form::open() never received
     * a 'method' option, so the <form> tag itself was always POST; the
     * actual create/update verb only ever went into the separate spoofed
     * _method field. Verified against the old view via
     * Collective\Html\FormBuilder::open()/getAppendage(), see
     * task-14-report.md).
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

        // "name": plain string field -> single-row <textarea> (the
        // original view never used Form::text() - every scalar,
        // non-enum, non-boolean, non-dateTime field went through
        // Form::textarea(..., rows=1); this rewrite preserves that).
        $response->assertSee('<textarea name="fields[name][input][0][value]" rows="1" class="form-control" cols="50"></textarea>', false);

        // "status": enum -> <select> with both options, none selected
        // (no resource, no old input).
        $response->assertSee('<select name="fields[status][input][0][value]" class="form-control">', false);
        $response->assertSee('<option value="draft">draft</option>', false);
        $response->assertSee('<option value="published">published</option>', false);

        // "active": boolean -> unchecked checkbox, no hidden "0" companion
        // field (laravelcollective's Form::checkbox() never emitted one -
        // verified against FormBuilder::checkable()/getCheckboxCheckedState(),
        // see task-14-report.md).
        $response->assertSee('<input type="checkbox" name="fields[active][input][0][value]" value="1">', false);
        $response->assertDontSee('fields[active][input][0][value_hidden]', false);

        // "description": html -> rich <textarea>, empty.
        $response->assertSee('name="fields[description][input][0][value]"', false);
        $response->assertSee('class="form-control html-richtext-input"', false);
    }

    /**
     * Edit form, with a persisted resource: every field repopulates from
     * the resource's current value (text-ish value, select selected-option,
     * checkbox checked-state), and the method-spoof field carries "put".
     */
    public function testEditFormRepopulatesFieldsFromResource()
    {
        $widget = $this->seedWidget('Widget One', [
            'status' => 'published',
            'active' => true,
            'description' => 'Rich text body',
        ]);

        $response = $this->get('/admin/widgets/' . $widget->id . '/edit');

        $response->assertStatus(200);

        $response->assertSee('name="_method" value="put"', false);

        // text-ish field repopulated with the resource's current value
        $response->assertSee('<textarea name="fields[name][input][0][value]" rows="1" class="form-control" cols="50">Widget One</textarea>', false);

        // select: the resource's current value is marked selected
        $response->assertSee('<option value="published" selected>published</option>', false);
        $response->assertSee('<option value="draft">draft</option>', false);

        // checkbox: checked because the resource's value is true
        $response->assertSee('<input type="checkbox" name="fields[active][input][0][value]" value="1" checked>', false);

        // textarea: repopulated with the resource's current value
        $response->assertSee('Rich text body', false);
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
