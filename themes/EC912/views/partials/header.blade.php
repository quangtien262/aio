@php
    $profile = $siteProfile ?? [];
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'Sudes Phone'))) ?: 'Sudes Phone';
    $hotline = trim((string) data_get($branding, 'support_hotline', '')) ?: '0399162342';
    $location = trim((string) data_get($branding, 'support_location', '')) ?: '7 cửa hàng';
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec12-header" id="top">
    <div class="ec12-container ec12-head-main">
        <a class="ec12-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $siteName }}">
            @else
                <span>{{ $siteName }}</span>
            @endif
        </a>
        <form class="ec12-search" action="{{ route('site.catalog.search') }}">
            <input name="q" placeholder="{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC912', app()->getLocale(), 'EC912.search_placeholder', 'Tìm sản phẩm...') }}" aria-label="Tìm sản phẩm">
            <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <a class="ec12-head-action ec12-store" href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i><span>@themeT('EC912.store_system', 'Hệ thống cửa hàng')<b>{{ $location }}</b></span></a>
        <a class="ec12-head-action" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone-volume"></i><span>@themeT('EC912.purchase_hotline', 'Gọi mua hàng')<b>{{ $hotline }}</b></span></a>
        @guest('customer')
            <button class="ec12-head-action" type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>@themeT('EC912.account', 'Tài khoản')<b>@themeT('EC912.login', 'Đăng nhập')</b></span></button>
        @else
            <a class="ec12-head-action" href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>@themeT('EC912.account', 'Tài khoản')<b>{{ auth('customer')->user()?->name }}</b></span></a>
        @endguest
        <a class="ec12-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-cart-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em><span>@themeT('EC912.cart', 'Giỏ hàng')</span></a>
        <button class="ec12-menu-toggle" type="button" data-ec12-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
    </div>
    <nav class="ec12-nav"><div class="ec12-container" data-ec12-nav>
        @foreach($nav as $item)
            <a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>
        @endforeach
    </div></nav>
</header>
@include('partials.storefront-language-switcher')
