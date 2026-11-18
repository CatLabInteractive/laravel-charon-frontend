<div class="form-group">
    @include('charonfrontend::widgets.label')

    <select class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName() }}">
        <option value=""></option>
        @foreach($field->getOptions() as $value => $display)
            <option value="{{ $value }}"{{ (string) $value === $selected ? ' selected' : '' }}>{{ $display }}</option>
        @endforeach
    </select>
</div>
