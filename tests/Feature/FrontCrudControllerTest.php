<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Smoke tests for FrontCrudController: table generation (the "index" admin
 * action) and form generation (the "create" admin action) produce output
 * without throwing, driven through a minimal in-test resource
 * (Tests\Support\Definitions\WidgetDefinition / Tests\Support\Models\Widget)
 * wired the way an Eukles Admin\* controller would wire a real one.
 *
 * This is explicitly scoped as a smoke test, not exhaustive coverage of
 * FrontCrudController.
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
     * FrontCrudController::create() -> formView() builds the field list,
     * the (empty, for this resource) linkable-relationship list and the
     * form action URL. WidgetFrontController::createFormView() is
     * overridden to hand that data back as JSON instead of rendering the
     * real crud/form.blade.php - see the class docblock for why (that view
     * needs the Form:: / Html:: facades from laravelcollective/html, which
     * has no Laravel 11/12 support and can't become a dependency here).
     */
    public function testCreateBuildsFormFieldsWithoutThrowing()
    {
        $response = $this->get('/admin/widgets/create');

        $response->assertStatus(200);
        $response->assertJson([
            'view' => 'charonfrontend::crud.form',
            'fieldNames' => ['name'],
            'linkableCount' => 0,
            'verb' => 'create',
            'method' => 'post',
        ]);

        $this->assertStringContainsString('/admin/widgets', $response->json('action'));
    }
}
