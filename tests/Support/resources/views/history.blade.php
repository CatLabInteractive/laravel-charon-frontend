@extends('charonfrontend::layouts.crud')

@section('cfcontent')
    @include('charonfrontend::crud.hub-header', [ 'hub' => $hub ])
    <p>History goes here.</p>
@endsection
