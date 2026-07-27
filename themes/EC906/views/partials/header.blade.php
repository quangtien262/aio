@php
    $profile = (array) ($siteProfile ?? []);
    $branding = (array) data_get($profile, 'branding', []);
    $logo = data_get($branding, 'logo_url');
    $siteName = data_get($profile, 'site_name', 'EGA Mini Mart');
    $nav = collect($primaryMenu ?? [])->filter()->values();
@endphp
<header class="ec96-header" id="top">
    <div class="ec96-container ec96-head-main">
        <button class="ec96-category-toggle" type="button" data-ec96-menu><i class="fa-solid fa-bars"></i><span>Danh mục sản phẩm</span></button>
        <a class="ec96-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><b>E</b><b>G</b><b>A</b><small>mini mart</small></span>@endif
        </a>
        <div class="ec96-head-actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>@endguest
            <a class="ec96-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em><span>Giỏ hàng</span></a>
        </div>
    </div>
    <nav class="ec96-nav" data-ec96-nav><div class="ec96-container">
        @forelse($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@empty
            <a href="#gioi-thieu">Giới thiệu</a><a href="#flash-sale">Khuyến mãi <i class="fa-solid fa-angle-down"></i></a><a href="#tin-tuc">Tin tức</a><a href="{{ route('site.catalog.search') }}">Kiểm tra đơn hàng</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.contact') }}">Hướng dẫn thiết lập</a>
        @endforelse
    </div></nav>
</header>
