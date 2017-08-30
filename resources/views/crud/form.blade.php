@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    {{ Html::ul($errors->all()) }}

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

    <div class="form-group">

        @foreach($fields as $field)

            {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
            {{ $field->getType() }}

            <?php
                $oldValue = (Form::old($field->getDisplayName())) ??
                    (isset($resource) ? $resource->getProperties()->getProperty($field)->getValue() : '');

                $properties = [
                    'class' => 'form-control'
                ];
            ?>

            {{ Form::hidden('fields[' . $field->getDisplayName() . '][type]', $field->getType()) }}

            @if($field->getType() === 'dateTime')
                <?php $dateTime = \Carbon\Carbon::parse($oldValue); ?>

                {{ Form::date('fields[' . $field->getDisplayName() . '][date]', $dateTime ? $dateTime->format('Y-m-d') : null, $properties) }}
                {{ Form::time('fields[' . $field->getDisplayName() . '][time]', $dateTime ? $dateTime->format('H:i') : null, $properties) }}
            @else
                <?php
                    $allowedValues = [];
                    foreach ($field->getAllowedValues() as $v) {
                        $allowedValues[$v] = $v;
                    }
                ?>
                @if(count($allowedValues) > 0)
                    {{ Form::select('fields[' . $field->getDisplayName() . '][value]', $allowedValues, $oldValue, $properties) }}
                @else
                    {{ Form::text('fields[' . $field->getDisplayName() . '][value]', $oldValue, $properties) }}
                @endif
            @endif

        @endforeach

        @foreach($linkables as $linkable)

            <?php
                $field = $linkable['field'];
                $values = $linkable['values'];

                if ($oldValue = Form::old($field->getDisplayName())) {}
                elseif(isset($resource)) {
                    $value = $resource->getProperties()->getProperty($field)->getValue();
                    $oldValue = $value['id'];
                }
            ?>

            {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
            {{ Form::select('linkable[' . $field->getDisplayName() . '][id]', $values, $oldValue, $properties) }}

        @endforeach


    </div>

    {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}

    {{ Form::close() }}

@endsection

