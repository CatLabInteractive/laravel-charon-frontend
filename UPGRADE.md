# Uploads on a failed save (2.0.6)

- **`UploadHandler::discard($id)` is new** and must be implemented. A file
  widget still stores its upload while the form is read (the API links
  resources by identifier), but when the API refuses the save, or anything
  throws, the front controller now calls `discard()` for every file stored
  during that save. Remove the stored file there (delete the asset).
- **The uploaded file survives the redirect.** On a failed save the file is
  copied to `storage/app/charonfrontend/kept-uploads` for the session that
  uploaded it, and the form shows "Use the file you uploaded: poster.png"
  (checked, with a preview for images up to 2 MB) instead of an empty file
  input. Submitting again stores it; unchecking it falls back to the
  picker. Kept files are removed after a day.
- Controllers that override `store()` or `update()` can wrap their own save
  in `saveWithUploads($request, fn () => ...)` to get the same behaviour.

# Rows open their show page, HTML on the show page (2.0.5)

- The table's View action is a visible eye button next to Edit and Delete
  (it was hidden in the row's ⋯ menu).
- Clicking anywhere on a row opens that button's page, the show page with
  its tabs. Links, buttons and form fields in the row keep their own
  behaviour; ctrl/cmd/middle click opens a new tab. Rows without a View
  button (not allowed, or no show route) stay inert. The script and style
  come from `crud/row-links.blade.php`, included by `layouts/crud`.
- `html()` fields render as HTML on the show page (Details), passed through
  Charon's HTML purifier again, since older values may be stored unpurified.

# Field groups and translated labels (2.0.4)

Nothing to change in an application; both are additions.

- **Field groups.** `addFieldGroup('Tickets', [ 'vat_percentage', 'team_size' ])`
  in a front controller's constructor shows those fields together in a
  fieldset on create and edit forms. A group sits where its first field
  would be; other fields keep their definition order. Views that include
  `crud/form-fields` without `$formSections` still render a flat list.
- **Translated labels.** The chrome, forms, delete page and widgets read
  their text from `charonfrontend::crud` (English and Dutch included), so
  they follow the app locale. Publish the `charonfrontend-lang` group to
  change the wording.
- **Linked records stay linked.** A relationship's current records are
  always among a form's options, also when the option list doesn't offer
  them (beyond its limit, another organisation's). Before, saving such a
  form silently unlinked them.
- **Create forms and saves check permission first.** `create()` and
  `store()` check `isMethodAllowed(create)` (the same check as the New
  button), `update()` loads the resource and checks `isMethodAllowed(edit)`,
  before widgets read the input, so a refused save no longer stores its
  uploads and a create form no longer lists options to whoever opens it.
- An unreadable date is a validation error instead of a 500.

**Custom show views (since 2.0.3).** A view that overrides `crud/view`
and loops over `$relationships` calling `['table']->render()` breaks:
`$relationships` now only holds `cell` (to one) and `links` (linked to
many) rows. Owned child lists are tabs: `$tabs` (key => label, url,
active), `$activeTab` and `$tabTable`, the table of the active child tab.
Controllers implementing `FrontCrudControllerContract` without the trait
need `getLinkableOptions()` (2.0.0) and `getBreadcrumbTrail()` (2.0.2).

# Page chrome, redirects and tabs (2.0.2, 2.0.3)

2.0.2 adds page chrome and changes where saving leads; 2.0.3 turns the show
page into a hub with tabs. Nothing changes in the
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
