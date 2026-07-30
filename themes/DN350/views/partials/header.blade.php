@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Prinash')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $menuItems = collect(data_get($themeShellData ?? [], 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label', data_get($item, 'title'))))->values();
    if ($menuItems->isEmpty()) {
        $menuItems = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
            ['label' => 'Dịch vụ', 'url' => route('site.home').'#dich-vu'],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Thư viện', 'url' => route('site.home').'#thu-vien'],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="dn350-header" data-dn350-header>
    <div class="dn350-top">
        <div class="dn350-container">
            <div class="dn350-top__contact">
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>
                <a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i>{{ $email }}</a>
            </div>
            <div class="dn350-top__social"><span>Kết nối với chúng tôi:</span><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-twitter"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-tiktok"></i><i class="fa-brands fa-instagram"></i></div>
        </div>
    </div>
    <div class="dn350-nav">
        <div class="dn350-container dn350-nav__inner">
            <a class="dn350-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }} - Trang chủ">
                @if($logo !== '')
                    <img src="{{ $logo }}" alt="{{ $siteName }}">@endif
            </a>
            <button class="dn350-menu-toggle" type="button" data-dn350-menu aria-expanded="false" aria-controls="dn350-menu"><i class="fa-solid fa-bars"></i></button>
            <nav id="dn350-menu" class="dn350-menu" data-dn350-nav>
                @foreach($menuItems as $item)
                    @include('theme-dn350::partials.menu-item', ['item' => $item, 'level' => 0])
                @endforeach
                @guest('admin') @guest('customer')
                    <button type="button" class="dn350-auth-link" data-xd-auth-open="login">@themeT('DN350.header.login', 'Đăng nhập')</button>
                    <button type="button" class="dn350-auth-link" data-xd-auth-open="register">@themeT('DN350.header.register', 'Đăng ký')</button>
                @endguest @endguest
            </nav>
            <a class="dn350-search" href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a class="dn350-quote" href="{{ route('site.contact') }}">@themeT('DN350.header.quote', 'Nhận báo giá')</a>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
