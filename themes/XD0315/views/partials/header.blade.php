<header class="af15-header">
    <div class="af15-logo-wrap">
        <a class="af15-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="af15-logo-mark">
                    <strong>ATHLETIC</strong>
                    <i>✊</i>
                    <em>FITNESS CENTER</em>
                </span>
            @endif
        </a>
    </div>
    <div class="af15-nav-shell">
        <button class="af15-menu-toggle" type="button" data-af15-menu-toggle aria-expanded="false">☰</button>
        <nav class="af15-nav" data-af15-nav aria-label="Dieu huong chinh">
            @foreach ($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                    {{ $item['label'] ?? 'Menu' }}
                    @if (! empty($item['children']))
                        <span aria-hidden="true">⌄</span>
                    @endif
                </a>
            @endforeach
        </nav>
        <div class="af15-actions">
            @guest('customer')
                <button type="button" data-xd-auth-open="login">Dang nhap</button>
                <button type="button" data-xd-auth-open="register">Dang ky</button>
            @else
                <a href="{{ route('customer.account') }}">Tai khoan</a>
            @endguest
            <span class="af15-flag">VN</span>
            <span class="af15-flag is-en">EN</span>
            <a class="af15-search" href="{{ route('site.catalog.search') }}" aria-label="Tim kiem">⌕</a>
            <button class="af15-burger" type="button" data-af15-menu-toggle aria-label="Menu">☰</button>
        </div>
    </div>
</header>
