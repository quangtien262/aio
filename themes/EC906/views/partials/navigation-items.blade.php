@foreach($navigationItems as $navigationIndex => $navigationItem)
    @php
        $navigationChildren = collect($navigationItem['children'] ?? [])->filter(fn ($child) => is_array($child) && filled($child['label'] ?? null))->values();
        $navigationId = $navigationPrefix.'-'.$navigationIndex;
    @endphp
    <li class="ec96-nav-item">
        <div class="ec96-nav-row">
            <a href="{{ $navigationItem['url'] ?? '#' }}" target="{{ $navigationItem['target'] ?? '_self' }}" @if(($navigationItem['target'] ?? '') === '_blank') rel="noopener noreferrer" @endif>{{ $navigationItem['label'] }}</a>
            @if($navigationChildren->isNotEmpty())<button type="button" class="ec96-submenu-toggle" aria-expanded="false" aria-controls="{{ $navigationId }}" aria-label="@themeT('navigation.expand', 'Mở menu con'): {{ $navigationItem['label'] }}"><span aria-hidden="true">⌄</span></button>@endif
        </div>
        @if($navigationChildren->isNotEmpty())
            <ul class="ec96-submenu" id="{{ $navigationId }}" hidden>@include('theme-ec906::partials.navigation-items', ['navigationItems' => $navigationChildren, 'navigationPrefix' => $navigationId])</ul>
        @endif
    </li>
@endforeach
