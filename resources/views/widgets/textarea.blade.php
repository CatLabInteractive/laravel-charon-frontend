<?php use CatLab\CharonFrontend\Support\FormValue; ?>
<div class="form-group">
    @include('charonfrontend::widgets.label')

    @foreach($values as $index => $value)
        <textarea id="{{ $index === 0 ? $field->getId() : $field->getId((string) $index) }}" name="{{ $field->getInputName($field->isMultiple() ? '[]' : '') }}"{!! FormValue::attributes(array_merge([ 'class' => 'form-control', 'data-charon-autogrow' => true ], $attributes, [ 'class' => trim('form-control ' . ($attributes['class'] ?? '')) ])) !!}>{{ $value }}</textarea>
    @endforeach
</div>

@include('charonfrontend::widgets.autogrow-script')
