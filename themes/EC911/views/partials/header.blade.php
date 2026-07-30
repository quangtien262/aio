@php
    $profile = (array) ($siteProfile ?? []);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = data_get($branding, 'logo_url');
    $siteName = data_get($profile, 'site_name', 'DIGITECH');
    $hotline = data_get($branding, 'support_hotline', '1900 6750');
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec11-header" id="top">
    <div class="ec11-container ec11-head-main">
        <a class="ec11-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">
            @else<span class="ec11-logo-mark"><i class="fa-solid fa-microchip"></i><b>DIGI</b><strong>TECH</strong></span>@endif
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
    </div>
    <nav class="ec11-nav"><div class="ec11-container">
        <button type="button" data-ec11-menu><i class="fa-solid fa-bars"></i> DANH MỤC SẢN PHẨM</button>
        <div data-ec11-nav>
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </div>
    </div></nav>
</header>
