{{-- One widget per field; see FrontCrudController::widgets() and CatLab\CharonFrontend\Widgets.
     Groups from FrontCrudController::addFieldGroup() render as fieldsets. --}}
@if(isset($formSections))
    @foreach($formSections as $formSection)
        @if($formSection['label'] === null)
            @foreach($formSection['fields'] as $formField)
                {!! $formField['widget']->render($formField['field']) !!}
            @endforeach
        @else
            <fieldset class="charon-field-group border rounded p-3 mb-3">
                <legend class="h5 w-auto px-2 mb-0">{{ $formSection['label'] }}</legend>
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
