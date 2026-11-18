{{-- The tabs of a show page; see FrontCrudController::getShowTabs() and addTab(). --}}
@if(!empty($tabs))
    <ul class="nav nav-tabs mb-3 charon-tabs">
        @foreach($tabs as $tab)
            <li class="nav-item">
                @if($tab['active'])
                    <a class="nav-link active" aria-current="page" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>
                @else
                    <a class="nav-link" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
