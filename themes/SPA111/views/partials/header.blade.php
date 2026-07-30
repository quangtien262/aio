@php
    $cartCount = collect(session('cart', []))->sum(fn ($row) => (int) data_get($row, 'quantity', 0));
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $companyName = trim((string) data_get($branding, 'company_name', 'Bean Spa')) ?: 'Bean Spa';
    $hotline = data_get($branding, 'support_hotline', '');
@endphp
<div class="sp11-topbar">
    <div class="sp11-container">
        <span>Chào mừng bạn đến với Bean Spa</span>
        <div><a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i> Tài khoản</a><a href="{{ route('site.contact') }}"><i class="fa-solid fa-shop"></i> Hệ thống chi nhánh</a></div>
    </div>
</div>
<header class="sp11-header" id="top">
    <div class="sp11-container sp11-header-row">
        <a class="sp11-logo" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $companyName }}">@endif
        </a>
        <button class="sp11-menu-toggle" type="button" data-spa111-menu-toggle aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="sp11-nav" data-spa111-menu>
            @foreach($nav as $index => $item)<a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </nav>
        <div class="sp11-actions">
            <a class="sp11-hotline" href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone-volume"></i><span>Gọi tư vấn<strong>{{ $hotline }}</strong></span></a>
            <button type="button" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            <a href="{{ route('customer.account') }}" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><b>0</b></a>
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>{{ $cartCount }}</b></a>
            <a class="sp11-book" href="{{ route('site.contact') }}">Đặt Lịch <i class="fa-regular fa-calendar-days"></i></a>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
