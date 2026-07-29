<?php
$properties = array_merge([], [
    'class' => 'form-control'
], $properties ?? []);

$fieldName = 'fields[' . $field->getDisplayName() . '][input][' . $index . '][value]';
?>

<input type="hidden" name="fields[{{ $field->getDisplayName() }}][type]" value="{{ $field->getType() }}">
<input type="hidden" name="fields[{{ $field->getDisplayName() }}][multiple]" value="{{ $field->isArray() ? 1 : 0 }}">

@if($field->getType() === 'dateTime')
    <?php
    $dateTime = $oldValue ? \Carbon\Carbon::parse($oldValue) : null;
    $dateName = 'fields[' . $field->getDisplayName() . '][input][' . $index . '][date]';
    $timeName = 'fields[' . $field->getDisplayName() . '][input][' . $index . '][time]';
    $dateValue = \CatLab\CharonFrontend\Support\FormValue::old($dateName, $dateTime ? $dateTime->format('Y-m-d') : null);
    $timeValue = \CatLab\CharonFrontend\Support\FormValue::old($timeName, $dateTime ? $dateTime->format('H:i') : null);
    ?>

    <div class="form-group">
        @if($showLabel)
            <label for="{{ $field->getLabel() }}">{{ \CatLab\CharonFrontend\Support\FormValue::labelText($field->getLabel(), $field->getLabel()) }}</label>

            @if($field->getDescription())
                <small class="form-text field-description">{{ $field->getDescription() }}</small>
            @endif
        @endif

        <div class="row">
            <div class="col-auto">
                <input type="date" name="{{ $dateName }}" value="{{ $dateValue }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($properties) !!}>
            </div>

            <div class="col-auto">
                <input type="time" name="{{ $timeName }}" value="{{ $timeValue }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($properties) !!}>
            </div>
        </div>
    </div>

@elseif($field->getType() === 'boolean')

    <?php $checked = \CatLab\CharonFrontend\Support\FormValue::checkboxChecked($fieldName, !!$oldValue); ?>

    <div class="form-group">
        <div class="form-check">

            @if($showLabel)
                <label for="{{ $field->getDisplayName() }}">{{ \CatLab\CharonFrontend\Support\FormValue::labelText($field->getDisplayName(), $field->getLabel()) }}</label>
            @endif

            <input type="checkbox" name="{{ $fieldName }}" value="1"{{ $checked ? ' checked' : '' }}>

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
    $selectedValue = \CatLab\CharonFrontend\Support\FormValue::old($fieldName, $oldValue);
    ?>
    <div class="form-group">
        @if($showLabel)
            <label for="{{ $field->getLabel() }}">{{ \CatLab\CharonFrontend\Support\FormValue::labelText($field->getLabel(), $field->getLabel()) }}</label>

            @if($field->getDescription())
                <small class="form-text field-description">{{ $field->getDescription() }}</small>
            @endif
        @endif

        @if(count($allowedValues) > 0)
            <select name="{{ $fieldName }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($properties) !!}>
                @foreach($allowedValues as $value => $display)
                    <option value="{{ $value }}"{{ (string) $value === (string) $selectedValue ? ' selected' : '' }}>{{ $display }}</option>
                @endforeach
            </select>
        @elseif($field->getType() === 'html')
            <?php
            $textareaAttrs = [ 'rows' => 5, 'class' => 'form-control html-richtext-input' ] + $properties;
            $textareaAttrs['cols'] = $textareaAttrs['cols'] ?? 50;
            ?>
            <textarea name="{{ $fieldName }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($textareaAttrs) !!}>{{ $selectedValue }}</textarea>
        @else
            <?php
            $textareaAttrs = [ 'rows' => 1 ] + $properties;
            $textareaAttrs['cols'] = $textareaAttrs['cols'] ?? 50;
            ?>
            <textarea name="{{ $fieldName }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($textareaAttrs) !!}>{{ $selectedValue }}</textarea>
        @endif
    </div>
@endif
