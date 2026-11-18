@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @include('charonfrontend::crud.tabs')

    @if($activeTab !== 'details')
        @if($tabTable)
            {{ $tabTable->render() }}
        @endif
    @else

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
                    @elseif($field->getField()->getType() === \CatLab\Requirements\Enums\PropertyType::HTML && is_string($field->getValue()))
                        {{-- html() fields render as HTML, cleaned again: values stored before the purifier existed aren't. --}}
                        <div class="charon-html-value">{!! \CatLab\Charon\CharonConfig::instance()->getHtmlPurifier()->purify($field->getValue()) !!}</div>
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
                <td>
                    @if(isset($relationship['links']))
                        @foreach($relationship['links'] as $link)
                            @if($link['url'])<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@else{{ $link['label'] }}@endif{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    @else
                        @include('table::cell', [ 'cell' => $relationship['cell'] ])
                    @endif
                </td>
            </tr>
        @endif

    @endforeach

    </table>

    @endif

@endsection

