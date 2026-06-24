@php
    $headerProductMenu = collect($headerProductMenu ?? $sidebarCategories ?? $productMenu ?? data_get($homeData ?? [], 'product_menu', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? null))
        ->values();
    $headerTopMenu = collect($headerTopMenu ?? $topMenuItems ?? $topMenu ?? data_get($homeData ?? [], 'top_menu', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? null))
        ->values();
@endphp

<div class="th-topbar">
    <div class="th-container th-topbar-inner">
        <div class="th-inline">
            <span>&#128205; {{ $contactLocation }}</span>
            <button type="button" class="th-inline-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}</button>
        </div>
        <div class="th-inline">
            <span>&#128222; Hotline: <span class="th-accent">{{ $contactHotline }}</span></span>
            <span>&#9993; Email: {{ $contactEmail }}</span>
            @if (!empty($customerAuth['is_authenticated']))
                <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>
                <form class="th-inline-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                    @csrf
                    <button type="submit" class="th-inline-action">Đăng xuất</button>
                </form>
            @else
                <button type="button" class="th-inline-action" data-open-auth-modal="register">Đăng ký</button>
                <button type="button" class="th-inline-action" data-open-auth-modal="login">Đăng nhập</button>
            @endif
        </div>
    </div>
</div>

<header class="th-header">
    <div class="th-container th-header-inner">
        <a class="th-logo" href="{{ route('site.home') }}">
            <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ $companyTitle ?: 'TH0020 Interior' }}">
        </a>
        <form class="th-search" method="GET" action="{{ route('site.catalog.search') }}" role="search">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm sofa, bàn ăn, đèn trang trí..." aria-label="Tìm kiếm sản phẩm nội thất" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
            <button type="submit">Tìm</button>
        </form>
        <a class="th-cart" href="{{ route('site.cart.index') }}">&#128722; {{ $cartSummary['count'] ?? 0 }} giỏ hàng</a>
    </div>
</header>

<nav class="th-main-nav">
    <div class="th-container th-main-nav-inner">
        <a class="th-home-link" href="{{ route('site.home') }}" aria-label="Trang chủ">&#8962;</a>
        <div class="th-main-nav-menu">
            <div class="th-nav-item th-nav-products">
                <a href="{{ route('site.catalog.search') }}" class="th-nav-link">
                    <span>Sản phẩm</span>
                    <span class="th-nav-caret">&#9662;</span>
                </a>
                <div class="th-nav-products-panel">
                    <div class="th-nav-products-inner">
                        <div class="th-nav-products-intro">
                            <span>@themeT('home.products_menu_kicker', 'TH0020 room catalog')</span>
                            <strong>@themeT('home.products_menu_title', 'Mua theo không gian sống')</strong>
                            <small>@themeT('home.products_menu_summary', 'Mega menu đa cấp gom nhóm phòng khách, phòng ngủ, bàn ăn, decor và vật liệu để khách đi nhanh tới đúng bộ sưu tập.')</small>
                        </div>
                        <div class="th-nav-products-grid">
                            @foreach ($headerProductMenu->take(8) as $category)
                                <div class="th-nav-category-card">
                                    <a href="{{ $category['url'] ?? route('site.catalog.search') }}" target="{{ $category['target'] ?? '_self' }}" class="th-nav-category-head">
                                        <span class="th-nav-category-icon">{{ $category['icon'] ?? '▣' }}</span>
                                        <strong>{{ $category['label'] ?? __('common.category') }}</strong>
                                    </a>
                                    <div class="th-nav-category-links">
                                        @forelse (collect($category['children'] ?? [])->take(4) as $child)
                                            <a href="{{ $child['url'] ?? ($category['url'] ?? route('site.catalog.search')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.child_group') }}</a>
                                        @empty
                                            <a href="{{ $category['url'] ?? route('site.catalog.search') }}" target="{{ $category['target'] ?? '_self' }}">Xem tất cả</a>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @foreach ($headerTopMenu as $menuItem)
                <div class="th-nav-item">
                    <a href="{{ $menuItem['url'] ?? route('site.home') }}" target="{{ $menuItem['target'] ?? '_self' }}" class="th-nav-link">
                        <span>{{ $menuItem['label'] ?? __('common.menu') }}</span>
                        @if (!empty($menuItem['children']))
                            <span class="th-nav-caret">&#9662;</span>
                        @endif
                    </a>
                    @if (!empty($menuItem['children']))
                        <div class="th-nav-simple-panel">
                            @foreach (collect($menuItem['children'])->take(6) as $child)
                                <a href="{{ $child['url'] ?? ($menuItem['url'] ?? route('site.home')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.menu') }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</nav>
