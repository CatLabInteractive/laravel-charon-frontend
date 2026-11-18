<?php

namespace Tests\Feature;

use CatLab\CharonFrontend\Widgets\CheckboxWidget;
use CatLab\CharonFrontend\Widgets\FileWidget;
use CatLab\CharonFrontend\Widgets\LinkableWidget;
use CatLab\CharonFrontend\Widgets\NumberWidget;
use CatLab\CharonFrontend\Widgets\SelectWidget;
use CatLab\CharonFrontend\Widgets\TextareaWidget;
use CatLab\CharonFrontend\Widgets\TextWidget;
use CatLab\CharonFrontend\Widgets\DateTimeWidget;
use CatLab\CharonFrontend\Widgets\WidgetRegistry;
use Illuminate\Http\UploadedFile;
use Tests\Support\Definitions\CategoryDefinition;
use Tests\Support\Definitions\TaggedWidgetDefinition;
use CatLab\CharonFrontend\Widgets\FormField;
use Illuminate\Http\Request;
use Tests\Support\FakeUploadHandler;
use Tests\Support\Models\Category;
use Tests\Support\Models\Widget;
use Tests\Support\TestCase;

/**
 * Forms are built from widgets: one per field, picked by WidgetRegistry,
 * rendering the input and reading it back into Charon input.
 */
class WidgetsTest extends TestCase
{
    private function fieldOf(string $displayName)
    {
        foreach ((new TaggedWidgetDefinition())->getFields() as $field) {
            if ($field->getDisplayName() === $displayName) {
                return $field;
            }
        }
        $this->fail('No field ' . $displayName);
    }

    public function testRegistryPicksAWidgetPerFieldType()
    {
        $registry = new WidgetRegistry();

        $this->assertInstanceOf(TextWidget::class, $registry->resolve($this->fieldOf('name')));
        $this->assertInstanceOf(SelectWidget::class, $registry->resolve($this->fieldOf('status')));
        $this->assertInstanceOf(CheckboxWidget::class, $registry->resolve($this->fieldOf('active')));
        $this->assertInstanceOf(TextareaWidget::class, $registry->resolve($this->fieldOf('description')));
        $this->assertInstanceOf(LinkableWidget::class, $registry->resolve($this->fieldOf('category')));
    }

    public function testRegistryOverridesWinOverTheDefaults()
    {
        $byField = new NumberWidget();
        $byType = new DateTimeWidget();
        $byRelationship = new FileWidget(new FakeUploadHandler());

        $registry = (new WidgetRegistry())
            ->forField('name', $byField)
            ->forType('html', $byType)
            ->forRelationship(CategoryDefinition::class, $byRelationship);

        $this->assertSame($byField, $registry->resolve($this->fieldOf('name')));
        $this->assertSame($byType, $registry->resolve($this->fieldOf('description')));
        $this->assertSame($byRelationship, $registry->resolve($this->fieldOf('category')));
    }

    public function testControllerRegisteredWidgetIsUsed()
    {
        $this->actingAsAuthorizedAdmin();

        $this->get('/admin/form-widgets/create')
            ->assertStatus(200)
            ->assertSee('<textarea id="field-name" name="fields[name]" class="form-control" rows="2"></textarea>', false);
    }

    public function testLabelsAreReadable()
    {
        $this->actingAsAuthorizedAdmin();

        $this->get('/admin/widgets/create')
            ->assertSee('<label for="field-description">Description</label>', false)
            ->assertSee('<label class="form-check-label" for="field-active">Active</label>', false);
    }

    /**
     * With a child controller registered for the related definition, the
     * options come from that controller's api index: its scope applies, so
     * records the index wouldn't show (another organisation's) aren't
     * offered either.
     */
    public function testLinkableOptionsComeFromTheChildControllersIndex()
    {
        $this->actingAsAuthorizedAdmin();
        $ours = $this->seedCategory('Tools');
        $theirs = $this->seedCategory('Other organisation tools');

        $response = $this->get('/admin/form-widgets/create');

        $response->assertStatus(200);
        $response->assertSee('<option value="' . $ours->id . '">Tools</option>', false);
        $response->assertDontSee('Other organisation tools');
    }

    public function testStoreReadsEveryWidget()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');

        $this->post('/admin/form-widgets', [
            'fields' => [
                'name' => 'Hammer',
                'status' => 'published',
                'active' => '1',
                'description' => '<p>Heavy</p>',
                'category' => [ 'id' => (string) $category->id ],
            ],
        ])->assertRedirect();

        $widget = Widget::where('name', 'Hammer')->firstOrFail();
        $this->assertSame('published', $widget->status);
        $this->assertTrue((bool) $widget->active);
        $this->assertSame('<p>Heavy</p>', $widget->description);
        $this->assertSame($category->id, (int) $widget->category_id);
    }

    public function testUpdateCanClearValues()
    {
        $this->actingAsAuthorizedAdmin();
        $category = $this->seedCategory('Tools');
        $widget = $this->seedWidget('Hammer', [ 'status' => 'published', 'active' => true, 'category_id' => $category->id ]);

        $this->put('/admin/form-widgets/' . $widget->id, [
            'fields' => [
                'name' => 'Hammer',
                'status' => '',
                'active' => '0',
                'description' => '',
                'category' => [ 'id' => '' ],
            ],
        ])->assertRedirect();

        $widget = $widget->fresh();
        $this->assertNull($widget->status);
        $this->assertFalse((bool) $widget->active);
        $this->assertNull($widget->category_id);
    }

    public function testFileWidgetRendersPreviewUploadPickerAndRemove()
    {
        $this->actingAsAuthorizedAdmin();
        $older = $this->seedCategory('older.png');
        $newer = $this->seedCategory('newer.png');
        $widget = $this->seedWidget('Hammer', [ 'category_id' => $older->id ]);

        $response = $this->get('/admin/upload-widgets/' . $widget->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('src="/preview/' . $older->id . '"', false);
        $response->assertSee('name="fields[category][file]" accept="image/*"', false);
        $response->assertSee('<option value="' . $older->id . '" selected>older.png</option>', false);
        $response->assertSee('name="fields[category][remove]"', false);
        $response->assertSeeInOrder([ 'newer.png', 'older.png' ]);
    }

    public function testFileWidgetStoresAnUploadAndLinksIt()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->put('/admin/upload-widgets/' . $widget->id, [
            'fields' => [
                'name' => 'Hammer',
                'category' => [ 'id' => '', 'file' => UploadedFile::fake()->image('poster.png', 60, 90) ],
            ],
        ])->assertRedirect();

        $stored = Category::where('name', 'poster.png')->firstOrFail();
        $this->assertSame($stored->id, (int) $widget->fresh()->category_id);
    }

    public function testFileWidgetPicksAndRemoves()
    {
        $this->actingAsAuthorizedAdmin();
        $existing = $this->seedCategory('existing.png');
        $widget = $this->seedWidget('Hammer');

        $this->put('/admin/upload-widgets/' . $widget->id, [
            'fields' => [ 'name' => 'Hammer', 'category' => [ 'id' => (string) $existing->id ] ],
        ])->assertRedirect();
        $this->assertSame($existing->id, (int) $widget->fresh()->category_id);

        $this->put('/admin/upload-widgets/' . $widget->id, [
            'fields' => [ 'name' => 'Hammer', 'category' => [ 'id' => (string) $existing->id, 'remove' => '1' ] ],
        ])->assertRedirect();
        $this->assertNull($widget->fresh()->category_id);
    }

    public function testFileWidgetRejectsTypesItDoesNotAccept()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget('Hammer');

        $this->from('/admin/upload-widgets/' . $widget->id . '/edit')
            ->put('/admin/upload-widgets/' . $widget->id, [
                'fields' => [
                    'name' => 'Hammer',
                    'category' => [ 'id' => '', 'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf') ],
                ],
            ])
            ->assertRedirect('/admin/upload-widgets/' . $widget->id . '/edit')
            ->assertSessionHasErrors('fields.category.file');

        $this->assertSame(0, Category::count());
    }

    public function testLinkableWidgetReadsARelationshipToMany()
    {
        $widget = new LinkableWidget();
        $field = new FormField($this->fieldOf('tags'));

        $request = Request::create('/', 'POST', [ 'fields' => [ 'tags' => [ 'ids' => [ '', '3', '5' ] ] ] ]);
        $this->assertSame([ 'items' => [ [ 'id' => '3' ], [ 'id' => '5' ] ] ], $widget->read($request, $field));

        // Only the hidden marker: everything unchecked unlinks all.
        $request = Request::create('/', 'POST', [ 'fields' => [ 'tags' => [ 'ids' => [ '' ] ] ] ]);
        $this->assertSame([ 'items' => [] ], $widget->read($request, $field));
    }

    public function testLinkableWidgetRendersCheckboxesForARelationshipToMany()
    {
        $field = (new FormField($this->fieldOf('tags')))->setOptions([ 3 => 'Tools', 5 => 'Garden' ]);

        $html = (new LinkableWidget())->render($field);

        $this->assertStringContainsString('<legend class="col-form-label pt-0">Tags</legend>', $html);
        $this->assertStringContainsString('<input type="hidden" name="fields[tags][ids][]" value="">', $html);
        $this->assertStringContainsString('name="fields[tags][ids][]" value="5"', $html);
        $this->assertStringContainsString('>Garden</label>', $html);
    }

    /**
     * Long option lists (people, ...) become a searchable picker instead of
     * a wall of checkboxes; underneath it's a multiple select with the same
     * input name, so reading the input doesn't change.
     */
    public function testLinkableWidgetRendersAPickerForLongLists()
    {
        $options = [];
        for ($i = 1; $i <= 7; $i++) {
            $options[$i] = 'Tag ' . $i;
        }
        $field = (new FormField($this->fieldOf('tags')))->setOptions($options);

        $html = (new LinkableWidget())->render($field);

        $this->assertStringContainsString('<input type="hidden" name="fields[tags][ids][]" value="">', $html);
        $this->assertStringContainsString('<select class="form-control" id="field-tags" name="fields[tags][ids][]" multiple size="8" data-charon-picker data-charon-picker-placeholder="Type to add…"', $html);
        $this->assertStringContainsString('<option value="6">Tag 6</option>', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('charon-picker', $html);

        // Up to the limit, checkboxes.
        $html = (new LinkableWidget(7))->render($field);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringNotContainsString('data-charon-picker', $html);
    }

    public function testDateTimeWidgetReadsDateAndTime()
    {
        $widget = new DateTimeWidget();
        $field = new FormField($this->fieldOf('name'));

        $request = Request::create('/', 'POST', [ 'fields' => [ 'name' => [ 'date' => '2026-12-17', 'time' => '20:30' ] ] ]);
        $this->assertSame('Thu, 17 Dec 26 20:30:00 +0000', $widget->read($request, $field));

        $request = Request::create('/', 'POST', [ 'fields' => [ 'name' => [ 'date' => '', 'time' => '' ] ] ]);
        $this->assertNull($widget->read($request, $field));
    }

    /**
     * A single-line input drops line breaks; a value that has them must be
     * edited in a textarea, or saving the form flattens it.
     */
    public function testTextWithLineBreaksIsEditedInATextarea()
    {
        $this->actingAsAuthorizedAdmin();
        $widget = $this->seedWidget("First line\nSecond line");

        $response = $this->get('/admin/widgets/' . $widget->id . '/edit');

        $response->assertStatus(200);
        $response->assertSee('<textarea id="field-name" name="fields[name]" class="form-control" rows="5">' . "First line\nSecond line" . '</textarea>', false);
        $response->assertDontSee('<input type="text" id="field-name"', false);
    }
}
