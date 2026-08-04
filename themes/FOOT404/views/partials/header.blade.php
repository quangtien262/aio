@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $siteName = trim((string) data_get($siteProfile ?? [], 'site_name', 'FOOT404'));
    $hotline = trim((string) data_get($branding, 'support_hotline'));
    $email = trim((string) data_get($branding, 'support_email'));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
    $cartCount = (int) data_get($shell, 'cart_summary.count', data_get($cartSummary ?? [], 'count', 0));
@endphp
<header class="f404-header" data-f404-header>
    <div class="f404-container f404-header__main">
        <a class="f404-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>F404</span><small>{{ $siteName }}</small>@endif
        </a>
        <form class="f404-search" action="{{ route('site.catalog.search') }}" method="get">
            <span>@themeT('FOOT404.product_categories', 'Danh mục sản phẩm')</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('FOOT404.search_placeholder', 'Tìm kiếm sản phẩm...')">
            <button type="submit" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="f404-support">
            <i class="fa-solid fa-phone-volume"></i>
            <span>@themeT('FOOT404.customer_support', 'Hỗ trợ khách hàng')@if($hotline)<a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}">{{ $hotline }}</a>@endif</span>
        </div>
        <div class="f404-header__actions">
            <button type="button" data-xd-auth-open="login" aria-label="@themeT('FOOT404.account', 'Tài khoản')"><i class="fa-regular fa-user"></i></button>
            <a class="f404-cart" href="{{ route('site.cart.index') }}" aria-label="@themeT('FOOT404.cart', 'Giỏ hàng')"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a>
            <button class="f404-menu-toggle" type="button" data-f404-menu-toggle aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
    <div class="f404-header__navwrap">
        <div class="f404-container f404-header__navrow">
            <a class="f404-category-button" href="#danh-muc"><i class="fa-solid fa-bars-staggered"></i> @themeT('FOOT404.product_categories', 'Danh mục sản phẩm')</a>
            <nav class="f404-nav" data-f404-nav>
                @foreach($nav as $item)<a href="{{ data_get($item, 'url', '#') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
                <button type="button" data-xd-auth-open="login">@themeT('FOOT404.login', 'Đăng nhập')</button>
                <button type="button" data-xd-auth-open="register">@themeT('FOOT404.register', 'Đăng ký')</button>
                <div class="f404-mobile-language">@include('partials.storefront-language-switcher')</div>
            </nav>
            <div class="f404-language">@include('partials.storefront-language-switcher')</div>
        </div>
    </div>
    @if($email)<a class="f404-sr-only" href="mailto:{{ $email }}">{{ $email }}</a>@endif
</header>
