@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    {{ Html::ul($errors->all()) }}

    {{ Form::open(array('url' => $action)) }}
    {{ method_field('DELETE') }}

    <p>Are you sure you want to remove this?</p>

    {{ Form::submit(ucfirst('Yes'), array('class' => 'btn btn-danger')) }}
    <a href="{{ $back }}" class="btn btn-primary">No</a>

    {{ Form::close() }}

@endsection

