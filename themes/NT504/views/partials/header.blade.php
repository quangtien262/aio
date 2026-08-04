@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '')));
    $phone = trim((string) ($branding['support_hotline'] ?? ''));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) {
        $nav = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#footer'],
            ['label' => 'Sản phẩm', 'url' => route('site.home').'#san-pham'],
            ['label' => 'Bảng màu', 'url' => route('site.home').'#khong-gian'],
            ['label' => 'Tư vấn màu', 'url' => route('site.contact')],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="n504-header">
    <div class="n504-summer"><div class="n504-container"><span><i class="fa-solid fa-gift"></i> Ưu đãi chào hè</span><span>Giảm đến <strong>15%</strong> cho đơn hàng từ 3 triệu</span><span><i class="fa-solid fa-truck"></i> Miễn phí giao hàng toàn quốc</span><a href="#khuyen-mai">Xem ngay <i class="fa-solid fa-arrow-right"></i></a></div></div>
    <div class="n504-container n504-head-main">
        <a class="n504-logo" href="{{ route('site.home') }}">
            @if($logo !== '')
                <img src="{{ $logo }}" alt="{{ $name }}">
            @elseif($name !== '')
                <span>{{ $name }}</span>
            @endif
        </a>
        <form class="n504-search" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Bạn cần tìm sản phẩm nào?"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="n504-head-meta"><span><i class="fa-regular fa-bell"></i><small>Tư vấn miễn phí<b>{{ $phone }}</b></small></span></div>
        <div class="n504-head-actions">
            <button type="button" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><b>0</b></button>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>@endguest
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>0</b></a>
        </div>
        <button class="n504-mobile-toggle" type="button" data-n504-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="n504-nav-bar"><div class="n504-container"><nav data-n504-nav>@foreach($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@endforeach</nav></div></div>
</header>
@include('partials.storefront-language-switcher')
