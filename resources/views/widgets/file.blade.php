<div class="form-group charon-file-field" data-charon-file>
    @include('charonfrontend::widgets.label', [ 'for' => $field->getId('file') ])

    <div class="charon-file-field-body" style="display: flex; align-items: flex-start;">
        <div class="charon-file-field-preview" style="flex: 0 0 160px; margin-right: 1rem; padding: .25rem; border: 1px solid #dee2e6; border-radius: .25rem; text-align: center;">
            <img src="{{ $previewUrl }}" alt="" style="max-width: 100%; max-height: 200px;" data-charon-file-preview{!! $previewUrl ? '' : ' hidden' !!}>
            <span class="text-muted small py-4" style="display: block;" data-charon-file-empty{!! $previewUrl ? ' hidden' : '' !!}>No file</span>
        </div>

        <div class="charon-file-field-controls" style="flex: 1 1 auto; min-width: 0;">
            <div class="mb-2">
                <small class="form-text text-muted mt-0">Upload a new file</small>
                <input type="file" class="form-control-file" id="{{ $field->getId('file') }}" name="{{ $field->getInputName('[file]') }}" accept="{{ $accept }}"
                       onchange="(function (input) { var f = input.closest('[data-charon-file]'); if (!input.files || !input.files[0]) { return; } var img = f.querySelector('[data-charon-file-preview]'); img.src = URL.createObjectURL(input.files[0]); img.hidden = false; f.querySelector('[data-charon-file-empty]').hidden = true; })(this)">
            </div>

            <div class="mb-2">
                <small class="form-text text-muted mt-0">or pick an existing one</small>
                <select class="form-control form-control-sm" id="{{ $field->getId() }}" name="{{ $field->getInputName('[id]') }}">
                    <option value="">(none)</option>
                    @foreach($options as $value => $display)
                        <option value="{{ $value }}"{{ (string) $value === $selected ? ' selected' : '' }}>{{ $display }}</option>
                    @endforeach
                </select>
            </div>

            @if($current !== null)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="{{ $field->getId('remove') }}" name="{{ $field->getInputName('[remove]') }}" value="1"{{ $field->old('remove') ? ' checked' : '' }}>
                    <label class="form-check-label" for="{{ $field->getId('remove') }}">Remove {{ \Illuminate\Support\Str::lower($field->getLabel()) }}</label>
                </div>
            @endif
        </div>
    </div>
</div>
