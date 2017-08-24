@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    {{ Html::ul($errors->all()) }}

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

    <div class="form-group">

        @foreach($fields as $field)

            {{ Form::label($field->getName(), ucfirst($field->getName())) }}
            {{ $field->getType() }}

            <?php
                $oldValue = (Form::old($field->getName())) ??
                    (isset($resource) ? $resource->getProperties()->getProperty($field)->getValue() : '');

                $properties = [
                    'class' => 'form-control'
                ];
            ?>

            {{ Form::hidden('fields[' . $field->getName() . '][type]', $field->getType()) }}

            @if($field->getType() === 'dateTime')
                <?php $dateTime = \Carbon\Carbon::parse($oldValue); ?>

                {{ Form::date('fields[' . $field->getName() . '][date]', $dateTime ? $dateTime->format('Y-m-d') : null, $properties) }}
                {{ Form::time('fields[' . $field->getName() . '][time]', $dateTime ? $dateTime->format('H:i') : null, $properties) }}
            @else
                {{ Form::text('fields[' . $field->getName() . '][value]', $oldValue, $properties) }}
            @endif

        @endforeach


    </div>

    {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}

    {{ Form::close() }}

@endsection

