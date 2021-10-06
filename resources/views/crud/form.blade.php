@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

    @include('charonfrontend::crud.form-fields')

    <div class="form-group row">
        {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}
    </div>

    {{ Form::close() }}

@endsection
