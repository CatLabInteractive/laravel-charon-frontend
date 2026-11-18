<?php

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Lists that leave fields out, actions of the application, errors under
 * their field and deletes the api refuses.
 */
class ActionsAndErrorsTest extends TestCase
{
    public function testTheListLeavesOutHiddenColumnsAndFilters()
    {
        $this->actingAsAuthorizedAdmin();
        $this->seedWidget('Hammer', [ 'status' => 'published' ]);

        $html = $this->get('/admin/action-widgets')->assertStatus(200)->getContent();

        $this->assertMatchesRegularExpression('~<th[^>]*>\s*(<a[^>]*>\s*)?Name~', $html);
        $this->assertDoesNotMatchRegularExpression('~<th[^>]*>\s*Status~', $html);
        $this->assertStringNotContainsString('name="status"', $html);
        $this->assertStringContainsString('name="name"', $html);

        // the plain list keeps both
        $plain = $this->get('/admin/widgets')->getContent();
        $this->assertMatchesRegularExpression('~<th[^>]*>\s*Status~', $plain);
        $this->assertStringContainsString('name="status"', $plain);
    }

    public function testRowActionsOfTheApplicationAreLinksFormsAndSelects()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $html = $this->get('/admin/action-widgets')->assertStatus(200)->getContent();
        $base = '/admin/action-widgets/' . $widget->id;

        // a labelled link
        $this->assertMatchesRegularExpression('~<a href="' . $base . '/preview" class="btn btn-sm btn-secondary table-action"\s*>Preview</a>~', $html);

        // a form with a confirmation
        $this->assertMatchesRegularExpression('~<form method="post" action="' . $base . '/publish"[^>]*onsubmit="return confirm\(&quot;Publish this widget\?&quot;\);"~', $html);
        $this->assertMatchesRegularExpression('~<button type="submit" class="btn btn-sm btn-success table-action"\s*>Publish</button>~', $html);
        $this->assertStringContainsString('name="_token"', $html);

        // a select in front of its button
        $this->assertMatchesRegularExpression('~<form method="post" action="' . $base . '/move"~', $html);
        $this->assertMatchesRegularExpression('~<select name="shelf"~', $html);
        $this->assertMatchesRegularExpression('~<option value="2"\s+selected\s*>Back</option>~', $html);

        // an entry of the menu
        $this->assertMatchesRegularExpression('~<a href="' . $base . '/history" class="dropdown-item"\s*>History</a>~', $html);

        // the built-in delete, drawn by configureRowAction()
        $this->assertMatchesRegularExpression('~class="btn btn-sm btn-outline-danger table-action"\s*>Delete</a>~', $html);
    }

    public function testRowActionsAreLeftOutWhereTheirConditionOrOptionsSayNo()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Draft');

        $html = $this->get('/admin/action-widgets')->assertStatus(200)->getContent();
        $base = '/admin/action-widgets/' . $widget->id;

        $this->assertStringContainsString($base . '/preview', $html);
        $this->assertStringNotContainsString($base . '/publish', $html);
        $this->assertStringNotContainsString($base . '/move', $html);
    }

    public function testPageActionsOfTheApplicationFollowEditAndDelete()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');
        $draft = $this->seedWidget('Draft');

        $html = $this->get('/admin/action-widgets/' . $widget->id)->assertStatus(200)->getContent();

        $this->assertMatchesRegularExpression(
            '~<form method="post" action="/admin/action-widgets/' . $widget->id . '/duplicate"[^>]*onsubmit="return confirm\(&quot;Make a copy\?&quot;\);"\s*>~',
            $html
        );
        $this->assertStringContainsString('<button type="submit" class="btn btn-outline-primary">Duplicate</button>', $html);
        $this->assertStringContainsString('<a href="/widgets/' . $widget->id . '" class="btn btn-secondary">Open</a>', $html);

        // a null url hides the button
        $this->get('/admin/action-widgets/' . $draft->id)
            ->assertSee('Duplicate')
            ->assertDontSee('>Open</a>', false);
    }

    public function testTheDeletePageAsksWhatTheControllerSays()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->get('/admin/action-widgets/' . $widget->id . '/delete')
            ->assertStatus(200)
            ->assertSee('<p>Retire Hammer?</p>', false)
            ->assertSee('value="Retire"', false);

        $this->get('/admin/widgets/' . $widget->id . '/delete')
            ->assertSee('value="Yes"', false);
    }

    public function testADeleteTheApiRefusesSaysWhyInsteadOfDeleted()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Locked');

        $response = $this->from('/admin/action-widgets/' . $widget->id . '/delete')
            ->delete('/admin/action-widgets/' . $widget->id);

        $response->assertRedirect('/admin/action-widgets/' . $widget->id . '/delete');
        // the reason, escaped, without the api's generic first line
        $this->assertSame('A locked widget &lt;b&gt;stays&lt;/b&gt;.', (string) session('message'));
        $this->assertDatabaseHas('widgets', [ 'id' => $widget->id ]);
    }

    public function testADeleteTheApiAcceptsStillDeletes()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->delete('/admin/action-widgets/' . $widget->id)->assertRedirect();

        $this->assertSame('Deleted.', (string) session('message'));
        $this->assertDatabaseMissing('widgets', [ 'id' => $widget->id ]);
    }

    public function testIssuesOfAFieldGoUnderThatFieldAndTheRestAboveThePage()
    {
        $this->actingAsAuthorizedAdmin();

        $response = $this->from('/admin/action-widgets/create')
            ->post('/admin/action-widgets', [ 'fields' => [ 'name' => 'Taken' ] ]);

        $response->assertRedirect('/admin/action-widgets/create');
        $response->assertSessionHasErrors([
            'fields.name' => 'That name is taken.',
            'fields.active' => 'Activate it later.',
        ]);
        $this->assertSame('There is no stock yet.', (string) session('message'));

        $html = $this->get('/admin/action-widgets/create')->assertStatus(200)->getContent();

        // under the label of the text field, and under the checkbox
        $this->assertMatchesRegularExpression('~<label for="field-name">Name</label>\s*<div class="invalid-feedback d-block field-error" role="alert">That name is taken\.</div>~', $html);
        $this->assertMatchesRegularExpression('~Activate it later\.</div>~', $html);
        // what was typed is still there
        $this->assertStringContainsString('Taken', $html);
    }

    public function testAnErrorWithoutIssuesShowsItsMessage()
    {
        $this->actingAsAuthorizedAdmin();

        $this->from('/admin/action-widgets/create')
            ->post('/admin/action-widgets', [ 'fields' => [ 'name' => 'Other' ] ])
            ->assertRedirect('/admin/action-widgets/create')
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Nothing to store here.', (string) session('message'));
    }
}
