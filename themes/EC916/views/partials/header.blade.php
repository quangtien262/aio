@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $cartCount = (int) data_get($cartSummary ?? [], 'count', 0);
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec16-header">
    <div class="ec16-container ec16-header-main">
        <a class="ec16-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'Bách Hóa Xanh Plus') }}">
            @else<i class="fa-solid fa-cart-shopping"></i><span>Bách Hóa <b>XANH+</b></span>@endif
        </a>
        <form class="ec16-search" action="{{ route('site.catalog.search') }}" method="get">
            <select aria-label="Danh mục"><option>Tất cả</option><option>Thực phẩm</option><option>Công nghệ</option><option>Làm đẹp</option></select>
            <input name="q" placeholder="@themeT('EC916.search_placeholder', 'Bạn muốn mua gì?')">
            <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <button class="ec16-account" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span><b>@themeT('EC916.login', 'Đăng nhập')</b><small>@themeT('EC916.account', 'Tài khoản và đơn hàng')</small></span></button>
        <a class="ec16-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><em>{{ $cartCount }}</em></a>
        <button class="ec16-menu-toggle" data-ec16-menu aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="ec16-benefits"><div class="ec16-container"><span><i class="fa-solid fa-truck-fast"></i> Miễn phí giao hàng</span><span><i class="fa-solid fa-rotate-left"></i> Dễ dàng đổi trả</span><span><i class="fa-solid fa-money-bill-wave"></i> Thanh toán linh hoạt</span></div></div>
    <nav data-ec16-nav>
        <div class="ec16-container">
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </div>
    </nav>
</header>
