@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            {{ Html::ul($errors->all()) }}
        </div>
    @endif

    {{ Form::open(array('url' => $action)) }}
    {{ method_field($verb) }}

    <div class="form-group">
        @foreach($fields as $field)
            <?php $index = 0; ?>
            @if($field->isArray())
                <?php
                    $oldValues = [];
                    $property = isset($resource) ? $resource->getProperties()->getProperty($field) : null;
                    if ($property) {
                        foreach ($property->getValue() as $v) {
                            $oldValues[] = $v;
                        }
                    }
                ?>

                @foreach($oldValues as $oldValue)
                    @include('charonfrontend::crud.field', [
                        'field' => $field,
                        'resource' => $resource,
                        'oldValue' => $oldValue,
                        'label' => $index === 0,
                        'index' => $index
                    ])

                    <?php $index ++; ?>
                @endforeach

                @include('charonfrontend::crud.field', [
                    'field' => $field,
                    'resource' => $resource,
                    'oldValue' => null,
                    'label' => $index === 0,
                    'index' => $index
                ])
            @else
                <?php
                    $oldValue = (Form::old($field->getDisplayName())) ??
                        (isset($resource) && $resource->getProperties()->getProperty($field)
                            ? $resource->getProperties()->getProperty($field)->getValue() : '');
                ?>

                @include('charonfrontend::crud.field', [
                    'field' => $field,
                    'resource' => $resource,
                    'oldValue' => $oldValue,
                    'label' => $index === 0,
                    'index' => $index
                ])
            @endif
        @endforeach

        <?php $linkableFields = []; ?>
        @foreach($linkables as $linkable)

            <?php
            $properties = [
                'class' => 'form-control'
            ];
            ?>

            <?php
            $field = $linkable['field'];
            $linkableFields[] = $field->getDisplayName();

            $extraProperties = [];
            $values = [];

            if ($linkable['field']->getCardinality() === \CatLab\Charon\Enums\Cardinality::MANY) {
                $extraProperties['multiple'] = 'multiple';
                $name = 'linkable[' . $field->getDisplayName() . '][][id]';
            } else {
                $name = 'linkable[' . $field->getDisplayName() . '][id]';
                $values[null] = '';
            }

            // add possible values
            foreach ($linkable['values'] as $k => $v) {
                $values[$k] = $v;
            }

            if ($oldValue = Form::old($field->getDisplayName())) {}
            elseif(isset($resource) && $resource->getProperties()->getProperty($field)) {
                $value = $resource->getProperties()->getProperty($field)->getValue();
                $oldValue = [];

                if ($linkable['field']->getCardinality() === \CatLab\Charon\Enums\Cardinality::MANY) {
                    foreach ($value as $v) {
                        $oldValue[] = $v['id'];
                    }
                } else {
                    $oldValue = $value['id'];
                }
            }
            ?>

            <div class="form-group row">
                {{ Form::label($field->getDisplayName(), ucfirst($field->getDisplayName())) }}
                {{ Form::select($name, $values, $oldValue, array_merge($properties, $extraProperties)) }}
            </div>

        @endforeach

        {{ Form::hidden('linkableFields', implode(',', $linkableFields)) }}

    </div>

    <div class="form-group row">
        {{ Form::submit(ucfirst($verb), array('class' => 'btn btn-primary')) }}
    </div>

    {{ Form::close() }}

@endsection
