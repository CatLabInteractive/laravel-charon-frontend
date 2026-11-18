@extends($layout)

@section('content')

    @if(!empty($chrome))
        @include('charonfrontend::crud.chrome')
    @endif

    @yield('cfcontent')

@endsection
