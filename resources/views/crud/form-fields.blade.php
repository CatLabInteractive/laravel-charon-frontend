{{-- One widget per field; see FrontCrudController::widgets() and CatLab\CharonFrontend\Widgets.
     Groups from FrontCrudController::addFieldGroup() render as fieldsets; a
     collapsed group is a <details>, open when one of its fields has an error.
     $sections (optional): the sections to draw, default all of $formSections. --}}
@php
    $drawSections = $sections ?? ($formSections ?? null);
@endphp
@if($drawSections !== null)
    @foreach($drawSections as $formSection)
        @if($formSection['label'] === null)
            @foreach($formSection['fields'] as $formField)
                {!! $formField['widget']->render($formField['field']) !!}
            @endforeach
        @elseif(!empty($formSection['collapsed']))
            @php
                $sectionHasError = false;
                if (isset($errors)) {
                    foreach ($formSection['fields'] as $formField) {
                        $dotName = $formField['field']->getDotName();
                        if ($errors->has($dotName) || $errors->has($dotName . '.*')) {
                            $sectionHasError = true;
                        }
                    }
                }
            @endphp
            <details class="charon-field-group charon-field-group-collapsed border rounded mb-3"@if($sectionHasError) open @endif>
                <summary class="h5 mb-0 px-3 py-2">{{ $formSection['label'] }}</summary>
                <div class="px-3 pb-1">
                    @if(!empty($formSection['description']))
                        <p class="text-muted small mb-3">{{ $formSection['description'] }}</p>
                    @endif
                    @foreach($formSection['fields'] as $formField)
                        {!! $formField['widget']->render($formField['field']) !!}
                    @endforeach
                </div>
            </details>
        @else
            <fieldset class="charon-field-group border rounded p-3 mb-3">
                <legend class="h5 w-auto px-2 mb-0">{{ $formSection['label'] }}</legend>
                @if(!empty($formSection['description']))
                    <p class="text-muted small mb-3">{{ $formSection['description'] }}</p>
                @endif
                @foreach($formSection['fields'] as $formField)
                    {!! $formField['widget']->render($formField['field']) !!}
                @endforeach
            </fieldset>
        @endif
    @endforeach
@else
    @foreach($formFields as $formField)
        {!! $formField['widget']->render($formField['field']) !!}
    @endforeach
@endif
