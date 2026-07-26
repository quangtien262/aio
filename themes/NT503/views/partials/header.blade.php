@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = $branding['company_name'] ?? 'WolfBed';
    $phone = $branding['support_hotline'] ?? '1900 6750';
    $logo = $branding['logo_url'] ?? '';
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) {
        $nav = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Flash Sale', 'url' => '#flash-sale'],
            ['label' => 'Sản phẩm', 'url' => '#san-pham'],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Giới thiệu', 'url' => '#footer'],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
            ['label' => 'Hệ thống cửa hàng', 'url' => '#footer'],
        ]);
    }
    $catalogCategories = collect(data_get($shell, 'catalog_categories', []))->take(8);
@endphp
<header class="n503-header">
    <div class="n503-container n503-head-main">
        <a class="n503-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<strong>WOLF<span>BED</span></strong>@endif
        </a>
        <form class="n503-search" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Tìm kiếm trong Wolf Bed"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="n503-head-meta"><span><i class="fa-solid fa-headset"></i><small>Bán hàng<b>{{ $phone }}</b></small></span><a href="#footer"><i class="fa-solid fa-store"></i><small>Hệ thống<b>Showroom</b></small></a></div>
        <div class="n503-head-actions">
            @guest('customer')
                <button type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>
                <button type="button" data-xd-auth-open="register" aria-label="Đăng ký"><i class="fa-regular fa-heart"></i><b>0</b></button>
            @else
                <a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>
            @endguest
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><b>0</b></a>
        </div>
        <button class="n503-mobile-toggle" type="button" data-n503-menu aria-label="Mở menu"><i class="fa-solid fa-bars"></i></button>
    </div>
    <div class="n503-nav-bar">
        <div class="n503-container">
            <div class="n503-category-menu">
                <button type="button" data-n503-category><i class="fa-solid fa-table-cells-large"></i> Danh mục sản phẩm <i class="fa-solid fa-chevron-down"></i></button>
                <div>@forelse($catalogCategories as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a>@empty<a href="#san-pham">Chăn ga</a><a href="#san-pham">Gối</a><a href="#san-pham">Nệm</a><a href="#san-pham">Phụ kiện</a>@endforelse</div>
            </div>
            <nav data-n503-nav>@foreach($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@endforeach</nav>
        </div>
    </div>
</header>
