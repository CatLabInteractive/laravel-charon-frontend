<div class="form-group">
    @include('charonfrontend::widgets.label')

    <div class="form-row">
        <div class="col-auto">
            <input type="date" class="form-control" id="{{ $field->getId() }}" name="{{ $field->getInputName('[date]') }}" value="{{ $date }}">
        </div>
        <div class="col-auto">
            <input type="time" class="form-control" id="{{ $field->getId('time') }}" name="{{ $field->getInputName('[time]') }}" value="{{ $time }}" aria-label="{{ $field->getLabel() }} (time)">
        </div>
    </div>
</div>
