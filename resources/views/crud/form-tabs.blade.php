{{-- Tabs above a create or edit form; see FrontCrudController::getFormTabs().
     A tab may carry a 'badge' (e.g. "draft") and be 'muted' (e.g. "+ French"). --}}
@if(!empty($tabs))
    <ul class="nav nav-tabs mb-3 charon-tabs charon-form-tabs">
        @foreach($tabs as $tab)
            <li class="nav-item">
                <a class="nav-link{{ !empty($tab['active']) ? ' active' : '' }}{{ !empty($tab['muted']) && empty($tab['active']) ? ' text-muted' : '' }}"
                   href="{{ $tab['url'] }}"@if(!empty($tab['active'])) aria-current="page"@endif>
                    {{ $tab['label'] }}
                    @if(!empty($tab['badge']))
                        <span class="badge {{ !empty($tab['badgeStyle']) ? 'badge-' . $tab['badgeStyle'] . ' bg-' . $tab['badgeStyle'] : 'badge-light bg-light text-dark' }} ml-1 ms-1">{{ $tab['badge'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
