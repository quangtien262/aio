<header class="xd2-header">
    <div class="xd2-utility">
        <div class="xd2-container xd2-utility__inner">
            <div><a href="tel:{{ $phoneHref ?? '' }}">{{ $hotline ?? '1900 9477' }}</a><a href="mailto:{{ $supportEmail ?? '' }}">{{ $supportEmail ?? 'admin@solerpanel.vn' }}</a></div>
            <div>Ngôn ngữ &nbsp; · &nbsp; Facebook &nbsp; YouTube</div>
        </div>
    </div>
    <div class="xd2-navigation">
        <div class="xd2-container xd2-navigation__inner">
            <a class="xd2-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt ?? $companyName ?? 'Soler Panel' }}">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $logoAlt ?? $companyName }}">
                @else
                    <span class="xd2-logo__mark">SP</span><span>Soler Panel<small>Energy Company</small></span>
                @endif
            </a>
            <button class="xd2-menu-toggle" type="button" data-xd-mobile-menu-toggle aria-expanded="false" aria-controls="xd-mobile-menu">Menu</button>
            <nav class="xd2-nav" aria-label="Menu chính">
                @foreach (($navItems ?? []) as $item)
                    <a class="{{ !empty($item['active']) ? 'is-active' : '' }}" href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}@if (!empty($item['children'])) <span>⌄</span> @endif</a>
                @endforeach
            </nav>
            <a class="xd2-search" href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm">⌕</a>
        </div>
        <div id="xd-mobile-menu" class="xd2-mobile-menu" data-xd-mobile-menu hidden>
            @foreach (($navItems ?? []) as $item)<a href="{{ $item['href'] ?? '#' }}" class="xd-mobile-link">{{ $item['label'] ?? 'Menu' }}</a>@endforeach
        </div>
    </div>
</header>
