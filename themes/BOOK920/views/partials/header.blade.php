@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url'));
    $cartCount = (int) data_get($cartSummary ?? [], 'count', 0);
@endphp
<header class="book20-header">
    <div class="book20-topbar">
        <div class="book20-container"><span><i class="fa-solid fa-phone"></i> {{ data_get($branding, 'support_hotline', '1900 9477') }}</span><span><i class="fa-regular fa-envelope"></i> {{ data_get($branding, 'support_email', 'hello@bookle.vn') }}</span><form action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Nhập từ khóa..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form><span>🇺🇸 🇻🇳</span></div>
    </div>
    <div class="book20-container book20-header-main">
        <a class="book20-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'Bookle') }}">@else<span><i class="fa-solid fa-book-open"></i> Bookle</span>@endif
        </a>
        <button class="book20-menu-toggle" data-book20-menu aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <nav data-book20-nav>
            <a href="{{ route('site.home') }}">Trang chủ</a><a href="#gioi-thieu">Giới thiệu</a><a href="#sach-hot">Sản phẩm</a><a href="#dich-vu">Dịch vụ</a><a href="#tin-tuc">Tin tức</a><a href="#thu-vien">Thư viện</a><a href="{{ route('site.contact') }}">Liên hệ</a>
        </nav>
        <div class="book20-actions"><button data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button><a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a></div>
    </div>
</header>
