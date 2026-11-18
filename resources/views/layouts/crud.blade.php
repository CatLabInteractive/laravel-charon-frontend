@extends($layout)

@section('content')

    @if(!empty($chrome))
        @include('charonfrontend::crud.chrome')
    @endif

    @yield('cfcontent')

    @include('charonfrontend::crud.row-links')

@endsection
