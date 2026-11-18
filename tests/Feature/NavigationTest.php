<?php

namespace Tests\Feature;

use Tests\Support\Models\Widget;
use Tests\Support\TestCase;

/**
 * Page chrome (title, breadcrumbs, actions) and where saving, cancelling and
 * deleting lead.
 */
class NavigationTest extends TestCase
{
    public function testShowPageHasTitleBreadcrumbsAndActions()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->get('/admin/widgets/' . $widget->id)
            ->assertStatus(200)
            ->assertSee('<h1 class="h2 mb-2 mr-3">Hammer</h1>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost/admin/widgets">Widgets</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Hammer</li>', false)
            ->assertSee('href="http://localhost/admin/widgets/' . $widget->id . '/edit?return=%2Fadmin%2Fwidgets%2F' . $widget->id . '" class="btn btn-primary">Edit</a>', false)
            ->assertSee('href="http://localhost/admin/widgets/' . $widget->id . '/delete?return=%2Fadmin%2Fwidgets%2F' . $widget->id . '" class="btn btn-danger">Delete</a>', false)
            ->assertDontSee('<h2>Details</h2>', false);
    }

    public function testIndexAndFormPagesHaveTitles()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->get('/admin/widgets')
            ->assertSee('<h1 class="h2 mb-2 mr-3">Widgets</h1>', false);

        $this->get('/admin/widgets/create')
            ->assertSee('<h1 class="h2 mb-2 mr-3">New widget</h1>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">New</li>', false);

        $this->get('/admin/widgets/' . $widget->id . '/edit')
            ->assertSee('<h1 class="h2 mb-2 mr-3">Hammer</h1>', false)
            ->assertSee('<a href="http://localhost/admin/widgets/' . $widget->id . '">Hammer</a>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Edit</li>', false);
    }

    /**
     * Rows open their show page: the view action is a visible button and
     * the row-click script uses it as the row's link.
     */
    public function testRowsLinkToTheirShowPage()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $html = $this->get('/admin/widgets')
            ->assertStatus(200)
            ->assertSee('table-action-view', false)
            ->assertSee('tr.charon-row-link', false)
            ->getContent();

        $this->assertMatchesRegularExpression('#href="http://localhost/admin/widgets/' . $widget->id . '"\s+class="[^"]*table-action-view#', $html);
    }

    public function testTheChromeFollowsTheAppLocale()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');
        $this->app->setLocale('nl');

        $this->get('/admin/widgets/' . $widget->id)
            ->assertSee('class="btn btn-primary">Bewerken</a>', false)
            ->assertSee('class="btn btn-danger">Verwijderen</a>', false);

        $this->get('/admin/form-widgets/' . $widget->id . '/edit')
            ->assertSee('Opslaan', false)
            ->assertSee('class="btn btn-link">Annuleren</a>', false);
    }

    public function testFieldGroupsRenderAsFieldsetsWhereTheirFirstFieldWas()
    {
        $this->actingAsAuthorizedAdmin();

        $html = $this->get('/admin/grouped-widgets/create')
            ->assertStatus(200)
            ->assertSee('<legend class="h5 w-auto px-2 mb-0">State</legend>', false)
            ->assertDontSee('Nothing</legend>', false)
            ->getContent();

        $name = strpos($html, 'name="fields[name]"');
        $legend = strpos($html, 'State</legend>');
        $active = strpos($html, 'name="fields[active]"');
        $status = strpos($html, 'name="fields[status]"');
        $end = strpos($html, '</fieldset>');
        $description = strpos($html, 'name="fields[description]"');

        $this->assertNotFalse($name);
        $this->assertNotFalse($description);
        // name, then the group (in group order: active, status), then the rest.
        $this->assertTrue($name < $legend && $legend < $active && $active < $status && $status < $end && $end < $description);
        $this->assertSame(1, substr_count($html, 'name="fields[status]"'));
    }

    public function testTheFormCarriesTheReturnUrlAndCancelsToIt()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->get('/admin/form-widgets/' . $widget->id . '/edit?return=admin/form-widgets')
            ->assertSee('<input type="hidden" name="return" value="/admin/form-widgets">', false)
            ->assertSee('<a href="/admin/form-widgets" class="btn btn-link">Cancel</a>', false);

        // Without one, Cancel goes to the show page.
        $this->get('/admin/form-widgets/' . $widget->id . '/edit')
            ->assertDontSee('name="return"', false)
            ->assertSee('<a href="http://localhost/admin/form-widgets/' . $widget->id . '" class="btn btn-link">Cancel</a>', false);
    }

    public function testSavingLandsOnTheShowPage()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->put('/admin/form-widgets/' . $widget->id, [
            'return' => '/admin/form-widgets',
            'fields' => [ 'name' => 'Sledgehammer', 'category' => [ 'id' => '' ] ],
        ])->assertRedirect('http://localhost/admin/form-widgets/' . $widget->id);

        $this->post('/admin/form-widgets', [
            'fields' => [ 'name' => 'Saw', 'category' => [ 'id' => '' ] ],
        ])->assertRedirect('http://localhost/admin/form-widgets/' . Widget::where('name', 'Saw')->value('id'));
    }

    public function testDeletingFromTheShowPageLandsOnTheIndex()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->delete('/admin/widgets/' . $widget->id, [ 'return' => '/admin/widgets/' . $widget->id ])
            ->assertRedirect('http://localhost/admin/widgets');

        $other = $this->seedWidget('Saw');
        $this->delete('/admin/widgets/' . $other->id, [ 'return' => '/admin/categories' ])
            ->assertRedirect('http://localhost/admin/categories');
    }

    public function testReturnUrlsOffSiteAreIgnored()
    {
        $this->actingAsAuthorizedAdmin();

        foreach ([ 'https://evil.example/x', '//evil.example/x', '/\\evil.example', 'javascript:alert(1)' ] as $return) {
            $widget = $this->seedWidget('Hammer');

            $this->get('/admin/form-widgets/' . $widget->id . '/edit?return=' . urlencode($return))
                ->assertDontSee('name="return"', false)
                ->assertDontSee('evil.example')
                ->assertDontSee('javascript:');

            $this->delete('/admin/widgets/' . $widget->id, [ 'return' => $return ])
                ->assertRedirect('http://localhost/admin/widgets');
        }
    }

    public function testNestedPagesStartWithTheParentsBreadcrumbs()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $base = '/admin/categories/' . $category->id . '/widgets';

        $this->get($base . '/' . $widget->id . '/edit')
            ->assertStatus(200)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost/admin/categories">Categories</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost/admin/categories/' . $category->id . '">Tools</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost' . $base . '">Widgets</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost' . $base . '/' . $widget->id . '">Hammer</a></li>', false);

        // Opened from the parent's page, saving goes back there.
        $this->put($base . '/' . $widget->id, [
            'return' => '/admin/categories/' . $category->id,
            'fields' => [ 'name' => 'Sledgehammer' ],
        ])->assertRedirect('http://localhost/admin/categories/' . $category->id);
    }

    public function testOwnedChildListsAreTabsOfTheShowPage()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $this->seedWidget('Hammer', [ 'category_id' => $category->id ]);

        $show = 'http://localhost/admin/hub-categories/' . $category->id;

        // Details: no widget table, but a tab for them and the app tab.
        $this->get('/admin/hub-categories/' . $category->id)
            ->assertStatus(200)
            ->assertSee('<a class="nav-link active" aria-current="page" href="' . $show . '">Details</a>', false)
            ->assertSee('<a class="nav-link" href="' . $show . '?tab=widgets">Widgets</a>', false)
            ->assertSee('<a class="nav-link" href="/admin/hub-categories/' . $category->id . '/history">History</a>', false)
            ->assertDontSee('Hammer');

        // The Widgets tab: the child table, whose links come back to this tab.
        $this->get('/admin/hub-categories/' . $category->id . '?tab=widgets')
            ->assertStatus(200)
            ->assertSee('<a class="nav-link active" aria-current="page" href="' . $show . '?tab=widgets">Widgets</a>', false)
            ->assertSee('Hammer')
            ->assertSee('return=%2Fadmin%2Fhub-categories%2F' . $category->id . '%3Ftab%3Dwidgets', false);
    }

    public function testAnAppPageShowsTheHubHeader()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');

        $this->get('/admin/hub-categories/' . $category->id . '/history')
            ->assertStatus(200)
            ->assertSee('<h1 class="h2 mb-2 mr-3">Tools</h1>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="http://localhost/admin/hub-categories/' . $category->id . '">Tools</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">History</li>', false)
            ->assertSee('<a class="nav-link active" aria-current="page" href="/admin/hub-categories/' . $category->id . '/history">History</a>', false)
            ->assertSee('History goes here.');
    }
}
