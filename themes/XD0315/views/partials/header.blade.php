<header class="af15-site-header">
    <div class="af15-site-header__inner">
        <a class="af15-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="af15-brand__fallback">
                    <strong>ATHLETIC</strong>
                    <span>FITNESS CENTER</span>
                </span>
            @endif
        </a>

        <div class="af15-nav-shell">
            <nav class="af15-nav" data-af15-nav aria-label="Dieu huong chinh">
                @foreach ($navItems ?? [] as $item)
                    <a href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                        {{ $item['label'] ?? 'Menu' }}
                        @if (! empty($item['children']))
                            <span aria-hidden="true">v</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="af15-actions">
                <span class="af15-auth">
                    @guest('customer')
                        <button type="button" data-xd-auth-open="login">Dang nhap</button>
                        <button type="button" data-xd-auth-open="register">Dang ky</button>
                    @else
                        <a href="{{ route('customer.account') }}">Tai khoan</a>
                    @endguest
                </span>
                <span class="af15-flag">VN</span>
                <span class="af15-flag af15-flag--en">EN</span>
                <a class="af15-search" href="{{ route('site.catalog.search') }}" aria-label="Tim kiem">o</a>
                <button class="af15-menu-toggle" type="button" data-af15-menu-toggle aria-expanded="false" aria-label="Menu">=</button>
            </div>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
