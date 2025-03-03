<?php
$properties = array_merge([], [
    'class' => 'form-control'
], $properties ?? []);
?>

{{ Form::hidden('fields[' . $field->getDisplayName() . '][type]', $field->getType()) }}
{{ Form::hidden('fields[' . $field->getDisplayName() . '][multiple]', $field->isArray() ? 1 : 0) }}

@if($field->getType() === 'dateTime')
    <?php $dateTime = $oldValue ? \Carbon\Carbon::parse($oldValue) : null; ?>

    <div class="form-group">
        @if($showLabel)
            {{ Form::label($field->getLabel(), $field->getLabel()) }}

            @if($field->getDescription())
                <small class="form-text field-description">{{ $field->getDescription() }}</small>
            @endif
        @endif

        <div class="row">
            <div class="col-auto">
                {{ Form::date('fields[' . $field->getDisplayName() . '][input]['.$index.'][date]', $dateTime ? $dateTime->format('Y-m-d') : null, $properties) }}
            </div>

            <div class="col-auto">
                {{ Form::time('fields[' . $field->getDisplayName() . '][input]['.$index.'][time]', $dateTime ? $dateTime->format('H:i') : null, $properties) }}
            </div>
        </div>
    </div>

@elseif($field->getType() === 'boolean')

    <div class="form-group">
        <div class="form-check">

            @if($showLabel)
                {{ Form::label($field->getDisplayName(), $field->getLabel()) }}
            @endif

            {{ Form::checkbox('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', 1, !!$oldValue) }}

        </div>

        @if($showLabel && $field->getDescription())
            <small class="form-text field-description">{{ $field->getDescription() }}</small>
        @endif
    </div>

@else
    <?php
    $allowedValues = [];
    foreach ($field->getAllowedValues() as $v) {
        $allowedValues[$v] = $v;
    }
    ?>
    <div class="form-group">
        @if($showLabel)
            {{ Form::label($field->getDisplayName(), $field->getLabel()) }}

            @if($field->getDescription())
                <small class="form-text field-description">{{ $field->getDescription() }}</small>
            @endif
        @endif

        @if(count($allowedValues) > 0)
            {{ Form::select('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', $allowedValues, $oldValue, $properties) }}
        @elseif($field->getType() === 'html')
            {{ Form::textarea('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', $oldValue, [ 'rows' => 5, 'class' => 'form-control html-richtext-input' ] + $properties) }}
        @else
            {{ Form::textarea('fields[' . $field->getDisplayName() . '][input]['.$index.'][value]', $oldValue, [ 'rows' => 1 ] + $properties) }}
        @endif
    </div>
@endif
