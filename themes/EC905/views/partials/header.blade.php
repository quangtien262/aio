@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $brand = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($brand, 'logo_url', ''));
    $hotline = data_get($brand, 'support_hotline', '0399162342');
    $menuItems = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec95-header">
    <div class="ec95-topbar"><div class="ec95-container"><span>Thỏa mãn nhu cầu người dùng · Giao hàng toàn quốc</span><nav><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> Hotline: {{ $hotline }}</a><a href="#du-an">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Tuyển dụng</a></nav></div></div>
    <div class="ec95-head-main"><div class="ec95-container">
        <a class="ec95-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name') }}">@else<b>EGO HOME</b><span>Nhà kiến tạo không gian đẹp</span>@endif</a>
        <form action="{{ route('site.catalog.search') }}" method="get"><input name="q" placeholder="Tìm kiếm sản phẩm..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <button class="ec95-account" type="button" data-auth-open="login"><i class="fa-solid fa-user"></i><span><b>Đăng nhập / Đăng ký</b>Tài khoản của bạn</span></button>
        <a class="ec95-action" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-cart-shopping"></i><span><b>0</b>Giỏ hàng</span></a>
        <a class="ec95-action" href="#"><i class="fa-solid fa-heart"></i><span><b>0</b>Yêu thích</span></a>
    </div></div>
    <nav class="ec95-nav"><div class="ec95-container">
        <button type="button" data-ec95-categories><i class="fa-solid fa-bars"></i> Danh mục sản phẩm</button>
        @foreach($menuItems as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
    </div></nav>
</header>
@include('partials.storefront-language-switcher')
