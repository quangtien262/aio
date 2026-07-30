@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $cartCount = (int) data_get($cartSummary ?? [], 'count', 0);
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
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
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </nav>
        <div class="ec17-tools">
            <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('EC917.search', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i></a>
            <button data-xd-auth-open="login" aria-label="@themeT('EC917.account', 'Tài khoản')"><i class="fa-regular fa-user"></i></button>
            <a class="ec17-cart" href="{{ route('site.cart.index') }}" aria-label="@themeT('EC917.cart', 'Giỏ hàng')"><i class="fa-solid fa-cart-shopping"></i><em>{{ $cartCount }}</em></a>
            <button class="ec17-menu-toggle" data-ec17-menu aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
