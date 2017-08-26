@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    <h2>Details</h2>

    <table class="table">
    @foreach($resource->getProperties()->getResourceFields()->getValues() as $field)

        <tr>
            <th>{{ ucfirst($field->getField()->getDisplayName()) }}</th>
            <th>{{ $field->getValue() }}</th>
        </tr>

    @endforeach
    </table>

    @foreach($relationships as $relationship)

        <h2>{{ $relationship['title'] }}</h2>
        {{ $relationship['table']->render() }}

    @endforeach

@endsection

