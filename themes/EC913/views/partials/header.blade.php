@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'NovaTech Mall'))) ?: 'NovaTech Mall';
    $hotline = trim((string) data_get($branding, 'support_hotline', '')) ?: '1900 6750';
    $nav = collect($primaryMenu ?? [])->filter()->values();
@endphp

<header class="ec13-header" id="top">
    <div class="ec13-topbar">
        <div class="ec13-container"><span><i class="fa-solid fa-bolt"></i> Công nghệ chính hãng, giá tốt mỗi ngày</span><div><a href="{{ route('site.blog.index') }}">Tin công nghệ</a><a href="{{ route('site.contact') }}">Tra cứu bảo hành</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a></div></div>
    </div>
    <div class="ec13-main-header">
        <div class="ec13-container ec13-head-main">
            <a class="ec13-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="ec13-logo-mark"><i class="fa-solid fa-bolt"></i></span><span><b>NOVA</b>TECH<small>Digital mall</small></span>
                @endif
            </a>
            <form class="ec13-search" action="{{ route('site.catalog.search') }}">
                <input name="q" placeholder="@themeT('EC913.search_placeholder', 'Bạn muốn tìm sản phẩm nào?')" aria-label="Tìm sản phẩm">
                <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a class="ec13-head-support" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-headset"></i><span>Tư vấn mua hàng<b>{{ $hotline }}</b></span></a>
            @guest('customer')
                <button class="ec13-icon-action" type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i><span>Tài khoản</span></button>
            @else
                <a class="ec13-icon-action" href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>{{ auth('customer')->user()?->name }}</span></a>
            @endguest
            <a class="ec13-icon-action ec13-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em><span>Giỏ hàng</span></a>
            <button class="ec13-menu-toggle" type="button" data-ec13-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
    <nav class="ec13-nav">
        <div class="ec13-container" data-ec13-nav>
            <button class="ec13-nav-category" type="button" data-ec13-mega-toggle><i class="fa-solid fa-bars-staggered"></i> Danh mục <i class="fa-solid fa-chevron-down"></i></button>
            @forelse($nav as $item)
                <a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>
            @empty
                <a class="active" href="{{ route('site.home') }}">Trang chủ</a>
                <a href="{{ route('site.catalog.search') }}">Sản phẩm <i class="fa-solid fa-chevron-down"></i></a>
                <a href="{{ route('site.home') }}#ban-chay">Khuyến mãi</a>
                <a href="{{ route('site.home') }}#laptop">Laptop</a>
                <a href="{{ route('site.blog.index') }}">Tin tức</a>
                <a href="{{ route('site.contact') }}">Liên hệ</a>
            @endforelse
            @auth('admin')<a class="ec13-admin-link" href="{{ route('admin.index') }}" target="_blank"><i class="fa-solid fa-gear"></i> Admin</a>@endauth
        </div>
    </nav>
</header>
