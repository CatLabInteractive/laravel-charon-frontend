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

        <p>Are you sure you want to remove this?</p>

        <input type="submit" value="Yes" class="btn btn-danger">
        <a href="{{ $back }}" class="btn btn-primary">No</a>

    </form>

@endsection
