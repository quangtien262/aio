<header class="xd4-header">
    <div class="xd4-container xd4-header__inner">
        <a class="xd4-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="xd4-brand__mark">L</span><strong>{{ $companyName }}</strong>@endif
        </a>
        <button class="xd4-menu-toggle" type="button" data-xd4-menu-toggle aria-expanded="false">Menu</button>
        <nav class="xd4-nav" data-xd4-nav aria-label="Äiá»u hÆ°á»›ng chÃ­nh">
            @foreach ($navItems ?? [] as $item)<a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>@endforeach
        </nav>
        <form class="xd4-search" method="GET" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="TÃ¬m kiáº¿m" aria-label="TÃ¬m kiáº¿m"><button type="submit" aria-label="TÃ¬m kiáº¿m">âŒ•</button></form>
        <a class="xd4-quote" href="#footer">TÆ° váº¥n vÃ  bÃ¡o giÃ¡ <span>â†’</span></a>
    </div>
</header>
