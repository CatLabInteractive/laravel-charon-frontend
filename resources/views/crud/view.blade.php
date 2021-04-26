@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    <h2>Details</h2>

    <table class="table">
    @foreach($resource->getProperties()->getResourceFields()->getValues() as $field)


        @if($field->getField()->isArray())

            @if(count($field->getValue()) === 0)
                <tr>
                    <th>{{ ucfirst($field->getField()->getDisplayName()) }}</th>
                    <td></td>
                </tr>
            @else
                <?php $index = 0; ?>
                @foreach($field->getValue() as $k => $v)
                    <tr>
                        @if(($index ++) === 0)
                            <th rowspan="{{count($field->getValue())}}">{{ ucfirst($field->getField()->getDisplayName()) }}</th>
                        @endif
                        <td>{{ $v }}</td>
                    </tr>
                @endforeach
            @endif

        @else
            <tr>
                <th>{{ ucfirst($field->getField()->getDisplayName()) }}</th>
                <td>{{ $field->getValue() }}</td>
            </tr>
        @endif


    @endforeach
    </table>

    @foreach($relationships as $relationship)

        <h2>{{ $relationship['title'] }}</h2>
        {{ $relationship['table']->render() }}

    @endforeach

@endsection

