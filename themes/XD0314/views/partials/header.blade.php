<header class="bb14-header">
    <div class="bb14-topbar">
        <div class="bb14-container bb14-topbar__inner">
            <div class="bb14-topbar__contact">
                <a href="tel:{{ $phoneHref }}"><span>☎</span>{{ $hotline }}</a>
                <span class="bb14-separator">|</span>
                <span><span>📍</span>{{ $supportAddress }}</span>
            </div>
            <div class="bb14-topbar__right">
                @guest('customer')
                    <button type="button" data-xd-auth-open="login">Đăng nhập</button>
                    <span>/</span>
                    <button type="button" data-xd-auth-open="register">Đăng ký</button>
                @else
                    <a href="{{ route('customer.account') }}">Tài khoản</a>
                @endguest
                <span class="bb14-connect">Kết nối chúng tôi</span>
                <a href="#footer" aria-label="Facebook">f</a>
                <a href="#footer" aria-label="Zalo">z</a>
                <a href="#footer" aria-label="Twitter">t</a>
                <a href="#footer" aria-label="Youtube">▶</a>
                <a href="#footer" aria-label="Pinterest">p</a>
                <span class="bb14-flag">VN</span>
                <span class="bb14-flag">EN</span>
            </div>
        </div>
    </div>

    <div class="bb14-navband">
        <div class="bb14-logo-panel">
            <a class="bb14-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
            </a>
        </div>

        <div class="bb14-container bb14-navband__inner">
            <button class="bb14-menu-toggle" type="button" data-bb14-menu-toggle aria-expanded="false">Menu</button>
            <nav class="bb14-nav" data-bb14-nav aria-label="Điều hướng chính">
                @foreach ($navItems ?? [] as $item)
                    <a href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                        {{ $item['label'] ?? 'Menu' }}
                        @if (! empty($item['children']))
                            <span aria-hidden="true">⌄</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <div class="bb14-nav-actions">
                <a class="bb14-search" href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm">⌕</a>
                <a class="bb14-quote" href="#footer">Tư vấn & Báo giá <span aria-hidden="true">✈</span></a>
            </div>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
