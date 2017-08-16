@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    {{ Html::ul($errors->all()) }}

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}



    <div class="form-group">

        @foreach($fields as $field)

            {{ Form::label($field->getName(), ucfirst($field->getName())) }}
            {{
                Form::text(
                    $field->getName(),
                    (Form::old($field->getName())) ?? (isset($resource) ? $resource->getProperties()->getProperty($field)->getValue() : ''),
                    [
                        'class' => 'form-control'
                        ]
                ) }}

        @endforeach


    </div>

    {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}

    {{ Form::close() }}

@endsection

