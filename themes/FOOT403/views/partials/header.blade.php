@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'HTRestaurant'))) ?: 'HTRestaurant';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $cartCount = max(0, (int) data_get($shell, 'cart_summary.count', 0));
    $locale = app()->getLocale();
    $searchUrl = route('site.catalog.search', ['locale' => $locale, 'searchSegment' => $locale === 'en' ? 'search' : 'tim-kiem']);
    $cartUrl = route('site.cart.index', ['locale' => $locale, 'cartSegment' => $locale === 'en' ? 'cart' : 'gio-hang']);
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#')])->values();
    if ($navItems->isEmpty()) $navItems = collect([['label'=>'Trang chủ','href'=>'#top'],['label'=>'Giới thiệu','href'=>'#gioi-thieu'],['label'=>'Menu','href'=>'#thuc-don'],['label'=>'Món ăn nổi bật','href'=>'#mon-noi-bat'],['label'=>'Món ngon mỗi ngày','href'=>'#danh-muc'],['label'=>'Tin tức','href'=>'#tin-tuc'],['label'=>'Liên hệ','href'=>'#lien-he']]);
@endphp
<header class="dr-header">
    <div class="dr-header__utility">
        <div class="dr-container dr-header__utility-inner">
            <span class="dr-header__welcome">Chào mừng bạn đến với {{ $companyName }}</span>
            <div class="dr-tools" aria-label="Tiện ích tài khoản và mua hàng">
                <a class="dr-tool-link" href="{{ $searchUrl }}" aria-label="Tìm kiếm">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                    <span>Tìm kiếm</span>
                </a>
                <a class="dr-tool-link dr-tool-link--cart" href="{{ $cartUrl }}" aria-label="Giỏ hàng, {{ $cartCount }} sản phẩm">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.3 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 8H7"></path><circle cx="10" cy="20" r="1"></circle><circle cx="18" cy="20" r="1"></circle></svg>
                    <span>Giỏ hàng</span>
                    <b class="dr-cart-count">{{ $cartCount }}</b>
                </a>
                <span class="dr-tools__divider" aria-hidden="true"></span>
                @guest('customer')
                    <button type="button" class="dr-account-link" data-dr-auth-open="login">Đăng nhập</button>
                    <span class="dr-account-separator" aria-hidden="true">/</span>
                    <button type="button" class="dr-account-link" data-dr-auth-open="register">Đăng ký</button>
                @else
                    <a class="dr-account-link" href="{{ route('customer.account') }}">Tài khoản</a>
                @endguest
            </div>
        </div>
    </div>
    <div class="dr-container dr-header__inner">
        <a class="dr-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">@if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="dr-brand__seal">♨</span><span><strong>Dola</strong><small>Restaurant</small></span>@endif</a>
        <button class="dr-menu-toggle" type="button" data-dr-menu-toggle aria-expanded="false">☰</button>
        <nav class="dr-nav" data-dr-menu>@foreach($navItems as $item)<a href="{{ $item['href'] }}">{{ $item['label'] }}</a>@endforeach</nav>
        <button class="dr-book" type="button" data-dr-order-open>Đặt bàn</button>
    </div>
</header>
