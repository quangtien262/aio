<header class="xd4-header">
    <div class="xd4-container xd4-header__inner">
        <a class="xd4-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>
        <button class="xd4-menu-toggle" type="button" data-xd4-menu-toggle aria-controls="xd4-main-nav" aria-expanded="false">Menu</button>
        <nav id="xd4-main-nav" class="xd4-nav" data-xd4-nav aria-label="Điều hướng chính">
            @foreach ($navItems ?? [] as $item)<a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>@endforeach
        </nav>
        <form class="xd4-search" method="GET" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="Tìm kiếm" aria-label="Tìm kiếm"><button type="submit" aria-label="Tìm kiếm">⌕</button></form>
        <a data-xd4-consultation-open aria-haspopup="dialog" aria-controls="xd4-consultation" class="xd4-quote" href="{{ route('site.home') }}#lien-he">Tư vấn và báo giá <span>→</span></a>

            @include('partials.storefront-language-switcher')
        </div>
</header>
