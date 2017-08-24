<table class="table">
    <tr>
        @foreach($columns as $column)
            <th>{{ $column }}</th>
        @endforeach
    </tr>

    @foreach($resources as $resource)
        <tr>
            @foreach($resource->toArray() as $col)
                @if(is_array($col))
                    <td>relationship?</td>
                @else
                    <td>{{ $col }}</td>
                @endif

            @endforeach

            <td>

                <?php
                    $id = ($resource->getIdentifiers()->getValues())[0]->getValue();
                ?>
                @foreach($actions as $action)
                    <?php
                        $parameters = [
                            'id' => $id
                        ];

                        if ($action['parameters'] !== null) {
                            $parameters = array_merge($action['parameters'], [ 'id' => $id ]);
                        }
                    ?>
                    <a href="{{ action($action['action'], $parameters) }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </td>
        </tr>
    @endforeach
</table>