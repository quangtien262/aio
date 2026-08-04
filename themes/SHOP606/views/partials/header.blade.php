@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'ATELIER'))) ?: 'ATELIER';
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="s606-header">
    <div class="s606-header-inner">
        <a class="s606-brand" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span>{{ $name }}</span>@endif</a>
        <button class="s606-menu-button" type="button" data-s606-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        <nav data-s606-nav>@forelse($nav as $item)<a href="{{ data_get($item, 'url', '#') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@empty<a href="{{ route('site.home') }}">Trang chủ</a><a href="#san-pham">Sản phẩm</a><a href="#khuyen-mai">Khuyến mãi</a><a href="#tin-tuc">Tin tức</a><a href="#bo-suu-tap">Bộ sưu tập</a>@endforelse</nav>
        <div class="s606-actions"><span class="s606-live"><i></i>LIVE</span><button type="button" data-s606-search aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button><button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button><a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>0</b></a></div>
    </div>
    <form class="s606-search" action="{{ route('site.catalog.search') }}" data-s606-search-form><input name="q" placeholder="Tìm kiếm sản phẩm"><button aria-label="Tìm"><i class="fa-solid fa-arrow-right"></i></button></form>
</header>
@include('partials.storefront-language-switcher')
