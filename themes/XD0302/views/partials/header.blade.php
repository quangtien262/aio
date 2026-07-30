<header class="xd2-header">
    <div class="xd2-utility">
        <div class="xd2-container xd2-utility__inner">
            <div><a href="tel:{{ $phoneHref ?? '' }}">{{ $hotline ?? '' }}</a><a href="mailto:{{ $supportEmail ?? '' }}">{{ $supportEmail ?? '' }}</a></div>
            <div class="xd2-utility__right">
                <span>Facebook &nbsp; · &nbsp; YouTube</span>
                @auth('customer')
                    <form class="xd2-utility__logout" method="POST" action="{{ route('customer.auth.logout') }}">
                        @csrf
                        <button type="submit">Đăng xuất</button>
                    </form>
                @else
                    <div class="xd2-auth-actions" aria-label="Tài khoản khách hàng">
                        <button type="button" data-xd-auth-open="login">Đăng nhập</button>
                        <button type="button" class="is-register" data-xd-auth-open="register">Đăng ký</button>
                    </div>
                @endauth
            </div>
        </div>
    </div>
    <div class="xd2-navigation">
        <div class="xd2-container xd2-navigation__inner">
            <a class="xd2-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt ?? $companyName ?? 'Soler Panel' }}">
                @if (filled($logoUrl ?? null))
                    <img src="{{ $logoUrl }}" alt="{{ $logoAlt ?? $companyName }}">@endif
            </a>
            <button class="xd2-menu-toggle" type="button" data-xd-mobile-menu-toggle aria-expanded="false" aria-controls="xd-mobile-menu">Menu</button>
            <nav class="xd2-nav" aria-label="Menu chính">
                <ul class="xd2-nav__list">
                    @include('theme-xd0302::partials.navigation-tree', ['items' => $navItems ?? [], 'level' => 0, 'mobile' => false])
                </ul>
            </nav>
            <a class="xd2-search" href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm">⌕</a>
        </div>
        <div id="xd-mobile-menu" class="xd2-mobile-menu" data-xd-mobile-menu hidden>
            <div class="xd2-mobile-menu__list">
                @include('theme-xd0302::partials.navigation-tree', ['items' => $navItems ?? [], 'level' => 0, 'mobile' => true])
            </div>
            @guest('customer')
                <div class="xd2-mobile-auth-actions">
                    <button type="button" data-xd-auth-open="login">Đăng nhập</button>
                    <button type="button" data-xd-auth-open="register">Đăng ký</button>
                </div>
            @endguest
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
