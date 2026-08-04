@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $siteName = trim((string) data_get($siteProfile ?? [], 'site_name', 'FOOT405'));
    $hotline = trim((string) data_get($branding, 'support_hotline'));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
    $cartCount = (int) data_get($shell, 'cart_summary.count', data_get($cartSummary ?? [], 'count', 0));
@endphp
<header class="f405-header" data-f405-header>
    <div class="f405-container f405-header__main">
        <a class="f405-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><i class="fa-solid fa-leaf"></i></span><strong>{{ $siteName }}</strong>@endif</a>
        <form class="f405-search" action="{{ route('site.catalog.search') }}" method="get"><span>@themeT('FOOT405.product_categories', 'Danh mục sản phẩm') <i class="fa-solid fa-angle-down"></i></span><input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('FOOT405.search_placeholder', 'Nhập từ khóa tìm kiếm...')"><button type="submit" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="f405-actions"><div class="f405-language">@include('partials.storefront-language-switcher')</div><button type="button" data-xd-auth-open="login" aria-label="@themeT('FOOT405.account', 'Tài khoản')"><i class="fa-regular fa-user"></i></button><a href="{{ route('site.cart.index') }}" class="f405-cart" aria-label="@themeT('FOOT405.cart', 'Giỏ hàng')"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a><button class="f405-menu-toggle" type="button" data-f405-menu-toggle aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button></div>
    </div>
    <div class="f405-navwrap"><div class="f405-container f405-navrow"><a class="f405-category-button" href="#danh-muc"><i class="fa-solid fa-bars-staggered"></i> @themeT('FOOT405.product_categories', 'Danh mục sản phẩm')</a><nav class="f405-nav" data-f405-nav>@foreach($nav as $item)<a href="{{ data_get($item, 'url', '#') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach<div class="f405-mobile-language">@include('partials.storefront-language-switcher')</div><button type="button" data-xd-auth-open="login">@themeT('FOOT405.login', 'Đăng nhập')</button><button type="button" data-xd-auth-open="register">@themeT('FOOT405.register', 'Đăng ký')</button></nav>@if($hotline)<a class="f405-hotline" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-headset"></i><span><strong>{{ $hotline }}</strong><small>@themeT('FOOT405.customer_support', 'Hỗ trợ khách hàng')</small></span></a>@endif</div></div>
</header>
