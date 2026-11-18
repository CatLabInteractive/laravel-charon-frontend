<label for="{{ $for ?? $field->getId() }}">{{ $field->getLabel() }}</label>
@if($field->getDescription())
    <small class="form-text field-description">{{ $field->getDescription() }}</small>
@endif
