
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
                    'showLabel' => $index === 0,
                    'index' => $index
                ])

                <?php $index ++; ?>
            @endforeach

            @include('charonfrontend::crud.field', [
                'field' => $field,
                'resource' => $resource,
                'oldValue' => null,
                'showLabel' => $index === 0,
                'index' => $index
            ])
        @else
            <?php
            $oldValue = (old($field->getDisplayName())) ??
                (isset($resource) && $resource->getProperties()->getProperty($field)
                    ? $resource->getProperties()->getProperty($field)->getValue() : '');
            ?>

            @include('charonfrontend::crud.field', [
                'field' => $field,
                'resource' => $resource,
                'oldValue' => $oldValue,
                'showLabel' => $index === 0,
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

        $isMany = $linkable['field']->getCardinality() === \CatLab\Charon\Enums\Cardinality::MANY;

        if ($isMany) {
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

        if ($oldValue = old($field->getDisplayName())) {}
        elseif(isset($resource) && $resource->getProperties()->getProperty($field)) {
            $value = $resource->getProperties()->getProperty($field)->getValue();
            $oldValue = [];

            if ($isMany) {
                foreach ($value as $v) {
                    $oldValue[] = $v['id'];
                }
            } else {
                $oldValue = $value['id'];
            }
        } else {
            $oldValue = null;
        }

        // repopulate from a redisplayed (failed-validation) form, same as
        // Form::select()'s own internal old-input lookup used to
        $oldValue = \CatLab\CharonFrontend\Support\FormValue::old($name, $oldValue);

        $selectAttributes = array_merge($properties, $extraProperties);
        ?>

        <div class="form-group">
            <label for="{{ $field->getDisplayName() }}">{{ \CatLab\CharonFrontend\Support\FormValue::labelText($field->getDisplayName(), ucfirst($field->getDisplayName())) }}</label>
            <select name="{{ $name }}"{!! \CatLab\CharonFrontend\Support\FormValue::attributes($selectAttributes) !!}>
                @foreach($values as $value => $display)
                    <?php
                    if ($isMany) {
                        $isSelected = is_array($oldValue) && (in_array($value, $oldValue, true) || in_array((string) $value, $oldValue, true));
                    } else {
                        $isSelected = ((string) $value === (string) $oldValue);
                    }
                    ?>
                    <option value="{{ $value }}"{{ $isSelected ? ' selected' : '' }}>{{ $display }}</option>
                @endforeach
            </select>
        </div>

    @endforeach

    <input type="hidden" name="linkableFields" value="{{ implode(',', $linkableFields) }}">
