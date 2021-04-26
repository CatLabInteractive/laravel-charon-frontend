<?php
$properties = [
    'class' => 'form-control'
];
?>

{{ Form::hidden('fields[' . $field->getDisplayName() . '][type]', $field->getType()) }}
{{ Form::hidden('fields[' . $field->getDisplayName() . '][multiple]', $field->isArray() ? 1 : 0) }}

@if($field->getType() === 'dateTime')
    <?php $dateTime = $oldValue ? \Carbon\Carbon::parse($oldValue) : null; ?>

    <div class="form-group row">
        @if($label)
            {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
        @endif
        {{ Form::date('fields[' . $field->getDisplayName() . '][input]['.$index.'][date]', $dateTime ? $dateTime->format('Y-m-d') : null, $properties) }}
        {{ Form::time('fields[' . $field->getDisplayName() . '][input]['.$index.'][time]', $dateTime ? $dateTime->format('H:i') : null, $properties) }}
    </div>

@elseif($field->getType() === 'boolean')

    <div class="form-check">

        @if($label)
            {{ Form::checkbox('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', 1, !!$oldValue) }}
        @endif
        {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}

    </div>

@else
    <?php
    $allowedValues = [];
    foreach ($field->getAllowedValues() as $v) {
        $allowedValues[$v] = $v;
    }
    ?>
    <div class="form-group row">
        @if($label)
            {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
        @endif

        @if(count($allowedValues) > 0)
            {{ Form::select('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', $allowedValues, $oldValue, $properties) }}
        @else
            {{ Form::textarea('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', $oldValue, [ 'rows' => 1 ] + $properties) }}
        @endif
    </div>
@endif
