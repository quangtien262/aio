@php
    $profile = (array) ($siteProfile ?? []);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = data_get($branding, 'logo_url');
    $siteName = data_get($profile, 'site_name', 'DIGITECH');
    $hotline = data_get($branding, 'support_hotline', '');
    $productMenu = collect(data_get($shell, 'product_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec11-header" id="top">
    <div class="ec11-container ec11-head-main">
        <a class="ec11-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif
        </a>
        <form class="ec11-search" action="{{ route('site.catalog.search') }}">
            <input name="q" placeholder="Tìm kiếm sản phẩm..." aria-label="Tìm kiếm sản phẩm">
            <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <a class="ec11-head-action" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone-volume"></i><span>Gọi mua hàng<b>{{ $hotline }}</b></span></a>
        @guest('customer')
            <button class="ec11-head-action" type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>Tài khoản<b>Đăng nhập</b></span></button>
        @else
            <a class="ec11-head-action" href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>Tài khoản<b>{{ auth('customer')->user()?->name }}</b></span></a>
        @endguest
        <a class="ec11-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em>Giỏ hàng</a>

            @include('partials.storefront-language-switcher')
        </div>
    <nav class="ec11-nav"><div class="ec11-container">
        <details class="ec11-category-menu" data-ec11-category-menu>
            <summary><i class="fa-solid fa-bars" aria-hidden="true"></i> Danh mục sản phẩm <span aria-hidden="true">⌄</span></summary>
            <div class="ec11-category-panel">
                <a class="ec11-category-all" href="{{ route('site.catalog.search') }}">Tất cả sản phẩm →</a>
                @if($productMenu->isNotEmpty())
                    <ul>@include('theme-ec911::partials.category-items', ['categoryItems' => $productMenu])</ul>
                @else
                    <p>Danh mục đang được cập nhật.</p>
                @endif
            </div>
        </details>
        <button type="button" class="ec11-nav-toggle" data-ec11-menu aria-label="Mở menu điều hướng" aria-controls="ec11-navigation" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i> Menu</button>
        <div id="ec11-navigation" data-ec11-nav>
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </div>
    </div></nav>
</header>
