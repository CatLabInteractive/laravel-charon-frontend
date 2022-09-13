@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    <h2>Details</h2>

    <table class="table">
    @foreach($resource->getProperties()->getResourceFields()->getValues() as $field)


        @if($field->getField()->isArray())

            @if(count($field->getValue()) === 0)
                <tr>
                    <th>{{ $field->getField()->getLabel() }}</th>
                    <td></td>
                </tr>
            @else
                <?php $index = 0; ?>
                @foreach($field->getValue() as $k => $v)
                    <tr>
                        @if(($index ++) === 0)
                            <th rowspan="{{count($field->getValue())}}">{{ $field->getField()->getLabel() }}</th>
                        @endif
                        <td>{{ $v }}</td>
                    </tr>
                @endforeach
            @endif

        @else
            <tr>
                <th>{{ ucfirst($field->getField()->getDisplayName()) }}</th>

                <td>
                    @if(is_array($field->getValue()))
                        <pre>{{ json_encode($field->getValue(), JSON_PRETTY_PRINT) }}</pre>
                    @else
                        {{ $field->getValue() }}
                    @endif
                </td>
            </tr>
        @endif


    @endforeach

    @foreach($relationships as $relationship)

        @if(!$relationship['multiple'])
            <tr>
                <th>{{ ucfirst($relationship['title']) }}</th>
                <th>{{ $relationship['table']->render() }}</th>
            </tr>
        @endif

    @endforeach

    </table>

    @foreach($relationships as $relationship)

        @if($relationship['multiple'])
            <h2>{{ $relationship['title'] }}</h2>
            {{ $relationship['table']->render() }}
        @endif

    @endforeach

@endsection

