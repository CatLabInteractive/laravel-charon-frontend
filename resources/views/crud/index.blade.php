@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    {{ $table->render() }}


    @foreach($actions as $action)
        <a class="btn btn-primary" href="{{ $action['url'] }}">
            {{ $action['label'] }}
        </a>
    @endforeach

@endsection

