@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url'));
    $cartCount = (int) data_get($cartSummary ?? [], 'count', 0);
@endphp
<header class="ec17-header">
    <div class="ec17-container ec17-header-inner">
        <a class="ec17-logo" href="{{ route('site.home') }}">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'EGA Furniture') }}">
            @else
                <span class="ec17-logo-mark"><i class="fa-solid fa-house-chimney"></i></span>
                <span><b>E G A</b><small>FURNITURE</small></span>
            @endif
        </a>
        <nav class="ec17-nav" data-ec17-nav>
            <a href="#san-pham">@themeT('EC917.products', 'Sản phẩm') <i class="fa-solid fa-chevron-down"></i></a>
            <a href="#danh-muc">@themeT('EC917.rooms', 'Phòng') <i class="fa-solid fa-chevron-down"></i></a>
            <a href="#khuyen-mai">@themeT('EC917.promotions', 'Khuyến mãi')</a>
            <a href="#cam-hung">@themeT('EC917.inspiration', 'Góc cảm hứng')</a>
            <a href="{{ route('site.contact') }}">@themeT('EC917.setup_guide', 'Hướng dẫn thiết lập')</a>
        </nav>
        <div class="ec17-tools">
            <span class="ec17-locale">🇻🇳 <i class="fa-solid fa-chevron-down"></i></span>
            <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('EC917.search', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i></a>
            <button data-xd-auth-open="login" aria-label="@themeT('EC917.account', 'Tài khoản')"><i class="fa-regular fa-user"></i></button>
            <a class="ec17-cart" href="{{ route('site.cart.index') }}" aria-label="@themeT('EC917.cart', 'Giỏ hàng')"><i class="fa-solid fa-cart-shopping"></i><em>{{ $cartCount }}</em></a>
            <button class="ec17-menu-toggle" data-ec17-menu aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</header>
