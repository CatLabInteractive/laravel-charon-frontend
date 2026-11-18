{{-- Title, breadcrumbs and actions above every crud page; see FrontCrudController::getPageChrome(). --}}
<div class="charon-page-header mb-3">
    @if(count($chrome['breadcrumbs']) > 1)
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @foreach($chrome['breadcrumbs'] as $crumb)
                    @if($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['label'] }}</li>
                    @elseif($crumb['url'])
                        <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                    @else
                        <li class="breadcrumb-item">{{ $crumb['label'] }}</li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <h1 class="h2 mb-2 mr-3">{{ $chrome['title'] }}</h1>

        @if(count($chrome['actions']))
            <div class="charon-page-actions mb-2">
                @foreach($chrome['actions'] as $pageAction)
                    <a href="{{ $pageAction['url'] }}" class="btn btn-{{ $pageAction['style'] ?? 'secondary' }}">{{ $pageAction['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>
