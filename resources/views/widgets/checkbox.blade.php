<div class="form-group">
    <div class="form-check">
        <input type="hidden" name="{{ $field->getInputName() }}" value="0">
        <input type="checkbox" class="form-check-input" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}" value="1"{{ $checked ? ' checked' : '' }}>
        <label class="form-check-label" for="{{ $field->getId() }}">{{ $field->getLabel() }}</label>
    </div>
    @if(isset($errors))
        @foreach($errors->get($field->getDotName()) as $fieldError)
            <div class="invalid-feedback d-block field-error" role="alert">{{ $fieldError }}</div>
        @endforeach
    @endif
    @if($field->getDescription())
        <small class="form-text field-description">{{ $field->getDescription() }}</small>
    @endif
</div>
