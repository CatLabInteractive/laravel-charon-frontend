@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    <table class="table">
    @foreach($resource->getProperties()->getResourceFields()->getValues() as $field)


        @if($field->getField()->isArray())

            @if(count($field->getValue()) === 0)
                <tr>
                    <th>{{ (new \CatLab\CharonFrontend\Widgets\FormField($field->getField()))->getLabel() }}</th>
                    <td></td>
                </tr>
            @else
                <?php $index = 0; ?>
                @foreach($field->getValue() as $k => $v)
                    <tr>
                        @if(($index ++) === 0)
                            <th rowspan="{{count($field->getValue())}}">{{ (new \CatLab\CharonFrontend\Widgets\FormField($field->getField()))->getLabel() }}</th>
                        @endif
                        <td>{{ $v }}</td>
                    </tr>
                @endforeach
            @endif

        @else
            <tr>
                <th>{{ (new \CatLab\CharonFrontend\Widgets\FormField($field->getField()))->getLabel() }}</th>

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
                <th>{{ $relationship['title'] }}</th>
                <td>@include('table::cell', [ 'cell' => $relationship['cell'] ])</td>
            </tr>
        @endif

    @endforeach

    </table>

    @foreach($relationships as $relationship)

        @if($relationship['multiple'])
            <h2 class="h4 mt-4">{{ $relationship['title'] }}</h2>
            {{ $relationship['table']->render() }}
        @endif

    @endforeach

@endsection

