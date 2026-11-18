<div class="form-group">
    @if($field->isMultiple())
        <fieldset>
            <legend class="col-form-label pt-0">{{ $field->getLabel() }}</legend>
            @if($field->getDescription())
                <small class="form-text field-description">{{ $field->getDescription() }}</small>
            @endif

            {{-- An empty marker, so unchecking everything still reaches the controller. --}}
            <input type="hidden" name="{{ $field->getInputName('[ids][]') }}" value="">

            @forelse($field->getOptions() as $value => $display)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="{{ $field->getId((string) $value) }}" name="{{ $field->getInputName('[ids][]') }}" value="{{ $value }}"{{ in_array((string) $value, $selected, true) ? ' checked' : '' }}>
                    <label class="form-check-label" for="{{ $field->getId((string) $value) }}">{{ $display }}</label>
                </div>
            @empty
                <p class="text-muted mb-0">Nothing to choose from.</p>
            @endforelse
        </fieldset>
    @else
        @include('charonfrontend::widgets.label')

        <select class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName('[id]') }}">
            <option value=""></option>
            @foreach($field->getOptions() as $value => $display)
                <option value="{{ $value }}"{{ in_array((string) $value, $selected, true) ? ' selected' : '' }}>{{ $display }}</option>
            @endforeach
        </select>
    @endif
</div>
