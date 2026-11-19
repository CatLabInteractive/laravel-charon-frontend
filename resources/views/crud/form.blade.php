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

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8"{!! $multipart ? ' enctype="multipart/form-data"' : '' !!}>
        @csrf
        @method($method)
        @if($return)
            <input type="hidden" name="{{ $returnParameter }}" value="{{ $return }}">
        @endif

        @include('charonfrontend::crud.form-fields')

        <div class="form-group">
            <input type="submit" value="{{ __('charonfrontend::crud.submit_' . $verb) }}" class="btn btn-primary">
            @if($cancel)
                <a href="{{ $cancel }}" class="btn btn-link">{{ __('charonfrontend::crud.cancel') }}</a>
            @endif
        </div>

    </form>

    @include('charonfrontend::widgets.autogrow-script')

@endsection
