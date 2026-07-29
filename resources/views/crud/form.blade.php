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

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8">
        @csrf
        @method($method)

        @include('charonfrontend::crud.form-fields')

        <div class="form-group">
            <input type="submit" value="{{ ucfirst($verb) }}" class="btn btn-primary">
        </div>

    </form>

@endsection
