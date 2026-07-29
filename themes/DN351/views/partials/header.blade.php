@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Meatlers')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '1900 9477'));
    $email = trim((string) ($branding['support_email'] ?? data_get($siteProfile ?? [], 'email', 'hello@meatlers.vn')));
    $menuItems = collect(data_get($themeShellData ?? [], 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label', data_get($item, 'title'))))->values();
    if ($menuItems->isEmpty()) {
        $menuItems = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
            ['label' => 'Sản phẩm', 'url' => route('site.home').'#san-pham'],
            ['label' => 'Dịch vụ', 'url' => route('site.services.index')],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Thư viện', 'url' => route('site.home').'#thu-vien'],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="dn351-header" data-dn351-header>
    <div class="dn351-topbar">
        <div class="dn351-container">
            <a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i>{{ $email }}</a>
            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>
            <span class="dn351-languages"><i class="fa-solid fa-globe"></i> VI · EN</span>
        </div>
    </div>
    <div class="dn351-navbar">
        <div class="dn351-container dn351-navbar__inner">
            <a class="dn351-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }} - Trang chủ">
                @if($logo !== '')
                    <img src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="dn351-logo__mark"><i class="fa-solid fa-cow"></i></span><strong>{{ $siteName }}</strong>
                @endif
            </a>
            <button class="dn351-menu-toggle" type="button" data-dn351-menu aria-expanded="false" aria-controls="dn351-menu"><i class="fa-solid fa-bars"></i></button>
            <nav id="dn351-menu" class="dn351-menu" data-dn351-nav>
                @foreach($menuItems as $item)
                    @include('theme-dn351::partials.menu-item', ['item' => $item, 'level' => 0])
                @endforeach
            </nav>
            <div class="dn351-tools">
                <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('DN351.header.search', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i><span>@themeT('DN351.header.search', 'Tìm kiếm')</span></a>
                @guest('admin') @guest('customer')
                    <button type="button" data-xd-auth-open="login" aria-label="@themeT('DN351.header.login', 'Đăng nhập')"><i class="fa-regular fa-user"></i></button>
                @endguest @endguest
                <a class="dn351-cart" href="{{ route('site.cart.index') }}" aria-label="@themeT('DN351.header.cart', 'Giỏ hàng')"><i class="fa-solid fa-cart-shopping"></i><b>{{ collect($cartItems ?? [])->sum('quantity') ?: 0 }}</b></a>
            </div>
        </div>
    </div>
</header>
