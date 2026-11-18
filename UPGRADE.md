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
  linkable one → select, linkable many → checkboxes. Labels use the
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
