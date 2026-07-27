@php
    $cartCount = collect(session('cart', []))->sum(fn ($row) => (int) data_get($row, 'quantity', 0));
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
@endphp
<div class="sp11-topbar">
    <div class="sp11-container">
        <span>Chào mừng bạn đến với Bean Spa</span>
        <div><span>🇻🇳 &nbsp;VI⌄</span><a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i> Tài khoản</a><a href="{{ route('site.contact') }}"><i class="fa-solid fa-shop"></i> Hệ thống chi nhánh</a></div>
    </div>
</div>
<header class="sp11-header" id="top">
    <div class="sp11-container sp11-header-row">
        <a class="sp11-logo" href="{{ route('site.home') }}" aria-label="Bean Spa">
            <span class="sp11-logo-mark"><i class="fa-solid fa-leaf"></i><b>B</b></span>
            <span><strong>Bean <em>Spa</em></strong><small>ĐẸP TRÊN CẢ ƯỚC MƠ</small></span>
        </a>
        <button class="sp11-menu-toggle" type="button" data-spa111-menu-toggle aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="sp11-nav" data-spa111-menu>
            <a class="is-active" href="{{ route('site.home') }}">Trang chủ</a>
            <a href="#gioi-thieu">Về chúng tôi</a>
            <a href="{{ route('site.catalog.search') }}">Sản phẩm⌄</a>
            <a href="#dich-vu">Dịch vụ⌄</a>
            <a href="#doi-ngu">Đội ngũ</a>
            <a href="#tin-tuc">Tin tức</a>
            <a href="#faq">Câu hỏi thường gặp</a>
            <a href="#lien-he">Liên hệ</a>
        </nav>
        <div class="sp11-actions">
            <a class="sp11-hotline" href="tel:19006750"><i class="fa-solid fa-phone-volume"></i><span>Gọi tư vấn<strong>{{ data_get($branding, 'support_hotline', '1900 6750') }}</strong></span></a>
            <button type="button" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            <a href="{{ route('customer.account') }}" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><b>0</b></a>
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>{{ $cartCount }}</b></a>
            <a class="sp11-book" href="#lien-he">Đặt Lịch <i class="fa-regular fa-calendar-days"></i></a>
        </div>
    </div>
</header>
