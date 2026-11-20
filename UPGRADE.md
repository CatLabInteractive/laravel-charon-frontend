# Filter conditions on the list (2.0.9)

- **Every filter above the list has a condition**: is, is not, contains (on
  `searchable()` fields), is empty, is not empty. Pick "is not empty" on a
  field to list the records where it is filled in. Requires charon ^1.13 and
  charon-laravel ^1.13 (which run the conditions) and laravel-table ^1.5
  (which draws them).
- The url carries one parameter per condition: `?name=value` (is, as before),
  `?name[neq]=value`, `?name[contains]=value`, `?name[empty]=1`,
  `?name[not_empty]=1`. The condition select renames the value input; it has
  no name of its own.
- An unknown or refused condition in the url is a 400. Blank values inside a
  condition are dropped from the request, like blank plain values.
- "is not" includes records without a value.

# Editing comfort: growing text areas, side column, save bar, parent on the form (2.0.8)

- **The save bar stays in view.** The form's buttons sit in
  `.charon-form-actions`, sticky at the bottom of the window, with
  "Unsaved changes" once a field changed; leaving the page with unsaved
  changes asks first. Save is a `<button>` now (was `<input type="submit">`)
  and comes after Cancel, on the right. A field
  that changes outside the form's inputs (a rich text editor in an iframe)
  dispatches an `input` event on its textarea to count as a change.
- **More save buttons**: `addSaveButton('Save and view', 'view')`; read
  which one was pressed in `redirectAfterSave()` with
  `getPressedSaveButton($request)`.
- **Field groups take options**: `addFieldGroup($label, $fields, [ 'aside' =>
  true, 'collapsed' => true, 'description' => '...' ])`. Aside groups go in a
  side column (`col-lg-4`; below the rest on a phone); collapsed groups are
  a `<details>`, opened when one of their fields has an error.
- **The parent on a child's edit form**: with `setParentController()`,
  `editParentOnForm($label, $groupOptions)` shows the parent's own edit form
  fields in a group (inputs under `parent[...]`, ids `parent-field-...`) and
  saves the parent first; a refused parent saves nothing and shows its
  errors under its fields. The parent needs the `FrontCrudController` trait
  (`getFormFieldsForChildForm()`, `saveFromChildForm()`). `FormField` takes the input root as a third constructor argument.
- **Tabs above a form**: override `getFormTabs($request, $action, $resource)`
  to return `[ 'label', 'url', 'active', 'badge', 'badgeStyle', 'muted' ]`
  tabs, e.g. one per language.
- `crud/form-fields` draws `$sections` when given (the form passes the main
  and the side column separately).

## Text areas grow with their text

- **Textareas grow** as you type, instead of scrolling inside a small box.
  The `rows` attribute is the minimum height; the corner can still be
  dragged. Every textarea of `TextareaWidget` (also the one a text with line
  breaks gets) has `data-charon-autogrow`; pass
  `[ 'data-charon-autogrow' => false ]` as attributes to keep a fixed box.
- **Textareas of the application can join in**: give them
  `data-charon-autogrow`. Every CRUD form loads the script once
  (`charonfrontend::widgets.autogrow-script`; include it yourself on a page
  that is not a CRUD form). Fields added later (repeaters, templates) and
  fields in a closed tab or `<details>` are sized when they appear. After
  setting a value from script, call `CharonFrontend.autogrow(textarea)`.
- Rich text editors replace their textarea and are not affected; make those
  grow in the editor's own configuration.

# Field errors, actions of the application, lists that leave fields out (2.0.7)

Needs `catlabinteractive/laravel-table` ^1.4.

- **Errors sit under their field.** What the API says about a field of the
  form (`error.issues.<field>`) is shown under that field's label, as
  `.invalid-feedback.field-error`, through Laravel's `$errors`
  (`fields.<name>`), and so also in the summary the form already shows
  above its fields. Issues about anything else still go in the `message`
  flash above the page, now without the API's generic first line and
  escaped. A controller that overrides `handleErrorResponse()` keeps its own
  behaviour. A custom widget view that does not include `widgets/label`
  shows field errors itself with `$errors->get($field->getDotName())`.
- **A delete the API refuses says why.** `destroy()` passes a 4xx JSON
  response to `handleErrorResponse($response, 'destroy')` instead of
  reporting "Deleted". Overrides of `destroy()` written for this can go.
- **The delete page can be worded.** Override `getDestroyConfirmText()` and
  `getDestroyConfirmLabel()` for a resource whose delete means something
  else ("Go back to the standard version?"). The `crud/destroy` view gets
  them as `$question` and `$confirmLabel`.
- **Row actions of the application.** `addRowAction($label, $url, $options)`
  in the constructor adds an action to every row: a labelled button by
  default, or an icon, a menu entry, a form (`method`), with a `confirm`
  question, a `condition` per row and a `select` in front of its button.
  `configureRowAction($action, $rowAction)` redraws a built-in View, Edit or
  Delete action (`$rowAction->setIcon(null)->showLabel()`).
- **Page actions of the application.** `addPageAction($label, $url, $options)`
  adds a button after Edit and Delete on the show page; with a `method` it
  is a form, with `confirm` it asks first.
- **Lists can leave fields out.** `getHiddenIndexColumns()` and
  `getHiddenIndexFilters()` return display names that get no column or no
  filter, for a field that has to be in the index for the code but means
  nothing to the reader.
- **Headers are labels.** Column headers and filter labels are the field's
  `label()`, or its name in words ("Created at" instead of `created_at`).
  This comes with laravel-table 1.4.

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
