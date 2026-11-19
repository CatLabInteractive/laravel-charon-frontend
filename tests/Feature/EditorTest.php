<?php

namespace Tests\Feature;

use Tests\Support\Models\Category;
use Tests\Support\Models\Widget;
use Tests\Support\TestCase;

/**
 * Editing comfort on forms: the parent's fields on a child's form, side
 * column and folded groups, tabs above the form, the save bar.
 */
class EditorTest extends TestCase
{
    private function base(Category $category): string
    {
        return '/admin/categories/' . $category->id . '/editor-widgets';
    }

    public function testTheEditFormHasTheParentsFieldsInTheSideColumn()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $html = $this->get($this->base($category) . '/' . $widget->id . '/edit')
            ->assertStatus(200)
            ->getContent();

        $aside = substr($html, strpos($html, '<div class="col-lg-4 charon-form-aside">'));
        $main = substr($html, 0, strpos($html, '<div class="col-lg-4 charon-form-aside">'));

        $this->assertStringContainsString('<div class="col-lg-8 charon-form-main">', $html);
        $this->assertStringContainsString('<legend class="h5 w-auto px-2 mb-0">Category</legend>', $aside);
        $this->assertStringContainsString('Shared by all widgets.', $aside);
        $this->assertStringContainsString('name="parent[name]" value="Tools"', $aside);
        $this->assertStringContainsString('id="parent-field-name"', $aside);
        $this->assertStringContainsString('name="fields[name]" value="Hammer"', $main);
    }

    public function testTheParentIsNotOnTheCreateForm()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');

        $this->get($this->base($category) . '/create')
            ->assertStatus(200)
            ->assertDontSee('parent[name]', false)
            ->assertDontSee('<div class="col-lg-4 charon-form-aside">', false);
    }

    public function testSavingSavesTheParentToo()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $this->put($this->base($category) . '/' . $widget->id, [
            'parent' => [ 'name' => 'Toolbox' ],
            'fields' => [ 'name' => 'Sledgehammer' ],
        ])->assertRedirect();

        $this->assertSame('Toolbox', $category->fresh()->name);
        $this->assertSame('Sledgehammer', $widget->fresh()->name);
    }

    public function testARefusedParentSavesNothingAndShowsTheErrorUnderItsField()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);
        $edit = $this->base($category) . '/' . $widget->id . '/edit';

        $this->from($edit)->put($this->base($category) . '/' . $widget->id, [
            'parent' => [ 'name' => '' ],
            'fields' => [ 'name' => 'Sledgehammer' ],
        ])->assertRedirect($edit)->assertSessionHasErrors('parent.name');

        $this->assertSame('Tools', $category->fresh()->name);
        $this->assertSame('Hammer', $widget->fresh()->name);

        // The form keeps what was typed.
        $this->get($edit)->assertSee('name="fields[name]" value="Sledgehammer"', false);
    }

    public function testAFormWithoutTheParentInputLeavesTheParentAlone()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $this->put($this->base($category) . '/' . $widget->id, [
            'fields' => [ 'name' => 'Sledgehammer' ],
        ])->assertRedirect();

        $this->assertSame('Tools', $category->fresh()->name);
        $this->assertSame('Sledgehammer', $widget->fresh()->name);
    }

    public function testFoldedGroupsTabsAndSaveButtons()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $this->get($this->base($category) . '/' . $widget->id . '/edit')
            ->assertSee('<details class="charon-field-group charon-field-group-collapsed border rounded mb-3">', false)
            ->assertSee('<summary class="h5 mb-0 px-3 py-2">More</summary>', false)
            ->assertSee('href="/this" aria-current="page"', false)
            ->assertSee('class="nav-link text-muted"', false)
            ->assertSee('draft</span>', false)
            ->assertSee('<button type="submit" name="charon_save" value="view" class="btn btn-outline-secondary">Save and view</button>', false)
            ->assertSee('<div class="charon-form-actions">', false)
            ->assertSee('data-charon-unsaved hidden', false);

        $this->put($this->base($category) . '/' . $widget->id, [
            'charon_save' => 'view',
            'fields' => [ 'name' => 'Sledgehammer' ],
        ])->assertRedirect('http://localhost/viewed');
    }
}
