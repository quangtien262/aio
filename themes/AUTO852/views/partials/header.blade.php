@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', 'ONYX DETAILING'));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<div class="a852-trustbar"><div class="a852-wrap">
    <span><i class="fa-solid fa-shield-halved"></i><b>Sản phẩm</b> chính hãng</span>
    <span><i class="fa-solid fa-truck-fast"></i><b>Giao hàng</b> toàn quốc</span>
    <span><i class="fa-solid fa-headset"></i><b>Tư vấn</b> miễn phí</span>
    <span><i class="fa-solid fa-gift"></i><b>Ưu đãi</b> đến 20%</span>
</div></div>
<header class="a852-header"><div class="a852-wrap a852-navrow">
    <a class="a852-logo" href="{{ $homeUrl }}">@if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span class="a852-logo-mark"><i class="fa-solid fa-gem"></i></span><span><b>ONYX</b><small>DETAILING</small></span>@endif</a>
    <button class="a852-menu" type="button" data-a852-menu aria-label="@themeT('menu.open', 'Mở menu')"><i class="fa-solid fa-bars"></i></button>
    <nav data-a852-nav>@forelse($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@empty<a href="{{ $homeUrl }}">Trang chủ</a><a href="#dich-vu">Dịch vụ</a><a href="#bang-gia">Bảng giá</a><a href="#san-pham">Sản phẩm</a><a href="#tin-tuc">Tin tức</a><a href="#lien-he">Liên hệ</a>@endforelse</nav>
    <button class="a852-icon a852-search-toggle" type="button" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
    @guest('customer')<button class="a852-icon" type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>@else<a class="a852-icon" href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}"><i class="fa-regular fa-user"></i></a>@endguest
    <a class="a852-icon" href="{{ route('site.cart.index', ['locale' => app()->getLocale()]) }}"><i class="fa-solid fa-cart-shopping"></i><em>{{ (int) ($cartCount ?? 0) }}</em></a>
    <a class="a852-book" href="#bang-gia"><i class="fa-regular fa-calendar-check"></i> Đặt lịch hẹn</a>

            @include('partials.storefront-language-switcher')
        </div></header>
