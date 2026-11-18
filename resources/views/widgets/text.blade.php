<?php use CatLab\CharonFrontend\Support\FormValue; ?>
<div class="form-group">
    @include('charonfrontend::widgets.label')

    @foreach($values as $index => $value)
        <input type="{{ $type }}" id="{{ $index === 0 ? $field->getId() : $field->getId((string) $index) }}" name="{{ $field->getInputName($field->isMultiple() ? '[]' : '') }}" value="{{ $value }}"{!! FormValue::attributes(array_merge([ 'class' => 'form-control' ], $attributes)) !!}>
    @endforeach
</div>
