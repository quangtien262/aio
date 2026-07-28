@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'Mộc Nhiên Craft'))) ?: 'Mộc Nhiên Craft';
    $hotline = trim((string) data_get($branding, 'support_hotline', '')) ?: '1900 6750';
    $nav = collect($primaryMenu ?? [])->filter()->values();
@endphp

<header class="ec14-header" id="top">
    <div class="ec14-topbar">
        <div class="ec14-container">
            <span><i class="fa-solid fa-bell"></i> Mua quà thủ công – Tặng gói quà tinh tế</span>
            <div><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> {{ $hotline }}</a><a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i> Cửa hàng</a></div>
        </div>
    </div>
    <div class="ec14-main-header">
        <div class="ec14-container ec14-head-main">
            <a class="ec14-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="ec14-logo-mark"><i class="fa-solid fa-seedling"></i></span>
                    <span><b>Mộc Nhiên</b><em>Craft</em><small>Từng sợi đan, gửi gắm yêu thương</small></span>
                @endif
            </a>
            <form class="ec14-search" action="{{ route('site.catalog.search') }}">
                <input name="q" placeholder="@themeT('EC914.search_placeholder', 'Tìm sản phẩm thủ công...')" aria-label="Tìm sản phẩm">
                <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <a class="ec14-icon-action" href="{{ route('site.home') }}#yeu-thich" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i></a>
            @guest('customer')
                <button class="ec14-icon-action ec14-account" type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>Tài khoản</span></button>
            @else
                <a class="ec14-icon-action ec14-account" href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>{{ auth('customer')->user()?->name }}</span></a>
            @endguest
            <a class="ec14-icon-action ec14-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em><span>Giỏ hàng</span></a>
            <button class="ec14-menu-toggle" type="button" data-ec14-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
    <nav class="ec14-nav">
        <div class="ec14-container" data-ec14-nav>
            <button class="ec14-nav-category" type="button"><i class="fa-solid fa-shapes"></i> Danh mục <i class="fa-solid fa-chevron-down"></i></button>
            @forelse($nav as $item)
                <a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>
            @empty
                <a class="active" href="{{ route('site.home') }}">Trang chủ</a>
                <a href="#cau-chuyen">Về chúng tôi</a>
                <a href="{{ route('site.catalog.search') }}">Sản phẩm</a>
                <a href="#bo-suu-tap">Bộ sưu tập</a>
                <a href="#cau-chuyen">Câu chuyện</a>
                <a href="{{ route('site.blog.index') }}">Tin tức</a>
                <a href="{{ route('site.contact') }}">Liên hệ</a>
            @endforelse
            <a class="ec14-promo-link" href="#sale"><i class="fa-solid fa-tag"></i> Ưu đãi hôm nay</a>
            @auth('admin')<a class="ec14-admin-link" href="{{ route('admin.index') }}" target="_blank"><i class="fa-solid fa-gear"></i></a>@endauth
        </div>
    </nav>
</header>
