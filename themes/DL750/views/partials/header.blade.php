@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Forest Camp'))) ?: 'Forest Camp';
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="dl-header">
    <div class="dl-head-top"><div class="dl-wrap">
        <div class="dl-contacts">@if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif @if($email)<a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i>{{ $email }}</a>@endif</div>
        <a class="dl-logo" href="{{ $homeUrl }}">@if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span><i class="fa-solid fa-mountain-sun"></i>{{ $company }}</span>@endif</a>
        <form class="dl-search" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Tìm kiếm sản phẩm..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
    </div></div>
    <div class="dl-nav-bar"><div class="dl-wrap">
        <button class="dl-menu" data-dl-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        <nav data-dl-nav>@forelse($nav as $item)<a href="{{ data_get($item, 'url') }}">{{ data_get($item, 'label') }}</a>@empty<a href="{{ $homeUrl }}">Trang chủ</a><a href="#gioi-thieu">Giới thiệu</a><a href="#san-pham">Sản phẩm</a><a href="#dich-vu">Dịch vụ</a><a href="#tin-tuc">Tin tức</a><a href="#lien-he">Liên hệ</a>@endforelse</nav>
        <div class="dl-actions">@guest('customer')<button data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i></a>@endguest<a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i></a><a class="dl-book" href="#lien-he"><i class="fa-solid fa-phone-volume"></i>Đặt ngay</a></div>
    </div></div>
</header>
<div class="dl-language">@include('partials.storefront-language-switcher')</div>
