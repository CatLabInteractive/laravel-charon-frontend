# Upgrading to 3.0

3.0 adds page chrome and changes where saving leads. Nothing changes in the
resource definitions or the API controllers.

## What changes for an application

- **Page chrome.** Every crud page gets a title, breadcrumbs and (on the
  show page) Edit and Delete buttons, from `crud/chrome.blade.php`, rendered
  by `layouts/crud.blade.php` above `cfcontent`. The show page loses its
  "Details" heading. Views that extend `charonfrontend::layouts.crud` with
  their own headings can switch it off by overriding `getPageChrome()` to
  return `[]`.
- **After saving** (create or edit) you land on the saved resource's show
  page, not on the index. A controller nested with `setParentController()`
  goes back to the page it was opened from (its `return` url) instead.
- **After deleting** you land on the `return` url, unless that is one of the
  deleted resource's own pages; otherwise on the index.
- **The return url travels in the form** as a hidden `return` field, not in
  the session (`frontcrud_index_redirect` is gone), so two tabs no longer
  overwrite each other. Only paths on the same site are accepted.
- **Forms have a Cancel link**: the return url, else the show page, else the
  index.
- `redirectBackToIndex()` is replaced by `redirectAfterSave()` and
  `redirectAfterDestroy()`. `FrontCrudControllerContract` gains
  `getBreadcrumbTrail(Request, $id = null)`; the trait implements it.
- `isMethodAllowed()` returns false without a logged-in user instead of
  failing.

## Nesting

For routes like `events/{event}/ticketCategories`:

```php
public function __construct()
{
    parent::__construct();
    $this->setParentController(EventController::class, 'event');
}
```

The breadcrumbs then read Events › Quiz night › Ticket categories › Paid
ticket, and saving a ticket category opened from the event page goes back to
the event.

## Customising

Override `getIndexTitle()`, `getResourceTitle()`, `getBreadcrumbs()` or
`getPageActions()` (merge with the parent's result to add buttons).

# Upgrading to 2.0

2.0 builds every create/edit form from **widgets**: one per field, picked from
the resource definition, each rendering its own inputs and reading them back
into Charon input.

## What changes for an application

- **Form input names.** Every input of a field now lives under
  `fields[<display name>]`: `fields[name]`, `fields[starts][date]`,
  `fields[category][id]`, `fields[tags][ids][]`. The old `fields[x][input][0][value]`,
  `linkable[...]` and `linkableFields` inputs are gone. Tests that post forms
  need the new names.
- **Views.** `crud/field.blade.php` is gone; widgets render
  `charonfrontend::widgets.*`. `crud/form-fields.blade.php` gets `$formFields`
  instead of `$fields` and `$linkables`, and `crud/form.blade.php` gets
  `$multipart`.
- **Removed:** `transformInputField()` and `isLinkableValid()`.
  `transformFormInput()` takes an optional `$action`.
- **Linkable options** come from `resolveLinkableOptions()`. When a child
  controller is registered (`setChildController()`) for the related
  definition, the options are that controller's api index, so its scope and
  authorization apply. Otherwise `resolveValues()` is used as before
  (`Model::all()`). Overriding `resolveValues()` still works for relationships
  without a child controller; override `resolveLinkableOptions()` to cover both.
- **`FrontCrudControllerContract`** gains `getLinkableOptions(Request)`; the
  trait implements it.
- **Defaults per type:** string → text input, integer/number → number input,
  boolean → checkbox (with a hidden 0, so unchecking is saved), dateTime →
  date + time, html → textarea with `html-richtext-input`, `enum()` → select,
  linkable one → select, linkable many → checkboxes up to 6 options, else a
  searchable picker with chips (since 2.1; a plain multiple select without
  JavaScript, same input names). Labels use the
  definition's `label()`, else the display name in words.

## Customising

In the front controller's constructor:

```php
$this->widgets()
    ->forField('description', new TextareaWidget())                // one field
    ->forType('html', new MyEditorWidget())                         // a type
    ->forRelationship(AssetResourceDefinition::class,               // a related definition
        new FileWidget(new MyUploadHandler(), 'image/*'));
```

A widget implements `CatLab\CharonFrontend\Widgets\Widget` (`render`, `read`,
`isMultipart`); extend `BaseWidget` to render a blade view. `FileWidget`
takes an `UploadHandler` that stores uploads and lists existing files, so the
library needs no knowledge of where files live.

`new LinkableWidget(20)` raises the number of options that still render as
checkboxes, e.g. `->forRelationship(TagResourceDefinition::class, new LinkableWidget(20))`.
