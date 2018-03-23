@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

    <div class="form-group">

        @foreach($fields as $field)

            <?php
            $oldValue = (Form::old($field->getDisplayName())) ??
                (isset($resource) && $resource->getProperties()->getProperty($field)
                    ? $resource->getProperties()->getProperty($field)->getValue() : '');

            $properties = [
                'class' => 'form-control'
            ];
            ?>

            {{ Form::hidden('fields[' . $field->getDisplayName() . '][type]', $field->getType()) }}

            @if($field->getType() === 'dateTime')
                <?php $dateTime = $oldValue ? \Carbon\Carbon::parse($oldValue) : null; ?>

                <div class="form-group row">
                    {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
                    {{ Form::date('fields[' . $field->getDisplayName() . '][date]', $dateTime ? $dateTime->format('Y-m-d') : null, $properties) }}
                    {{ Form::time('fields[' . $field->getDisplayName() . '][time]', $dateTime ? $dateTime->format('H:i') : null, $properties) }}
                </div>

            @elseif($field->getType() === 'boolean')

                <div class="form-check">

                    {{ Form::checkbox('fields[' . $field->getDisplayName() . '][value]', 1, !!$oldValue) }}
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
                    {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
                    @if(count($allowedValues) > 0)
                        {{ Form::select('fields[' . $field->getDisplayName() . '][value]', $allowedValues, $oldValue, $properties) }}
                    @else
                        {{ Form::text('fields[' . $field->getDisplayName() . '][value]', $oldValue, $properties) }}
                    @endif
                </div>
            @endif

        @endforeach

        @foreach($linkables as $linkable)

            <?php
            $field = $linkable['field'];

            $values = [];

            if (!$field->isRequired()) {
                $values[null] = '';
            }

            foreach ($linkable['values'] as $k => $v) {
                $values[$k] = $v;
            }

            if ($oldValue = Form::old($field->getDisplayName())) {}
            elseif(isset($resource) && $resource->getProperties()->getProperty($field)) {
                $value = $resource->getProperties()->getProperty($field)->getValue();
                $oldValue = $value['id'];
            }
            ?>

            <div class="form-group row">
                {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
                {{ Form::select('linkable[' . $field->getDisplayName() . '][id]', $values, $oldValue, $properties) }}
            </div>

        @endforeach


    </div>

    <div class="form-group row">
        {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}
    </div>

    {{ Form::close() }}

@endsection

