@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if(!$errors->isEmpty())
        <div class="alert alert-warning">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('charonfrontend::crud.form-tabs', [ 'tabs' => $tabs ?? [] ])

    @php
        $allSections = $formSections ?? [ [ 'label' => null, 'fields' => $formFields, 'aside' => false ] ];
        $mainSections = array_values(array_filter($allSections, function ($section) { return empty($section['aside']); }));
        $asideSections = array_values(array_filter($allSections, function ($section) { return !empty($section['aside']); }));
    @endphp

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8" class="charon-form" data-charon-form{!! $multipart ? ' enctype="multipart/form-data"' : '' !!}>
        @csrf
        @method($method)
        @if($return)
            <input type="hidden" name="{{ $returnParameter }}" value="{{ $return }}">
        @endif

        @if(count($asideSections) > 0)
            <div class="row">
                <div class="col-lg-8 charon-form-main">
                    @include('charonfrontend::crud.form-fields', [ 'sections' => $mainSections ])
                </div>
                <div class="col-lg-4 charon-form-aside">
                    @include('charonfrontend::crud.form-fields', [ 'sections' => $asideSections ])
                </div>
            </div>
        @else
            @include('charonfrontend::crud.form-fields', [ 'sections' => $allSections ])
        @endif

        <div class="charon-form-actions">
            <span class="charon-form-unsaved" data-charon-unsaved hidden>{{ __('charonfrontend::crud.unsaved_changes') }}</span>
            @if($cancel)
                <a href="{{ $cancel }}" class="btn btn-link">{{ __('charonfrontend::crud.cancel') }}</a>
            @endif
            @foreach(($saveButtons ?? []) as $saveButton)
                <button type="submit" name="{{ $saveButtonName ?? 'charon_save' }}" value="{{ $saveButton['value'] }}" class="btn btn-outline-secondary">{{ $saveButton['label'] }}</button>
            @endforeach
            <button type="submit" class="btn btn-primary">{{ __('charonfrontend::crud.submit_' . $verb) }}</button>
        </div>

    </form>

    @include('charonfrontend::widgets.autogrow-script')
    @include('charonfrontend::crud.form-script')

@endsection
