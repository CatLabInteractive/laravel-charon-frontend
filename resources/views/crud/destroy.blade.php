@extends('charonfrontend::layouts.crud')

@section('cfcontent')

    @if($errors->any())
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $action }}" accept-charset="UTF-8">
        @csrf
        @method('DELETE')
        @if($return)
            <input type="hidden" name="{{ $returnParameter }}" value="{{ $return }}">
        @endif

        <p>Are you sure you want to remove this?</p>

        <input type="submit" value="Yes" class="btn btn-danger">
        <a href="{{ $back }}" class="btn btn-secondary">No</a>

    </form>

@endsection
