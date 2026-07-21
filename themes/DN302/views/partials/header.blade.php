@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Website')));
    $hotline = $branding['support_hotline'] ?? '1900 9477';
    $email = $branding['support_email'] ?? data_get($siteProfile ?? [], 'email', 'admin@demo.web30s.vn');
    $address = trim((string) ($branding['support_location'] ?? ''));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $menuItems = collect(data_get(
        $themeShellData ?? [],
        'top_menu',
        data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))
    ))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label', data_get($item, 'title'))))->values();
    if ($menuItems->isEmpty()) {
        $menuItems = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
            ['label' => 'Sản phẩm', 'url' => route('site.home').'#san-pham'],
            ['label' => 'Dịch vụ', 'url' => route('site.home').'#dich-vu'],
            ['label' => 'Dự án', 'url' => route('site.home').'#du-an'],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="dn-header" data-dn-header>
    <div class="dn-header-inner dn-container">
        <a class="dn-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }} - Trang chủ">
            @if($logo !== '')
                <img src="{{ $logo }}" alt="{{ $siteName }}">
            @else
                <i class="fa-regular fa-window-maximize"></i>
                <span>{{ $siteName }}<small>{{ data_get($siteProfile ?? [], 'description', 'Windows &amp; Doors') }}</small></span>
            @endif
        </a>
        <div class="dn-head-main">
            <div class="dn-topbar">
                @if($address !== '')
                    <span><i class="fa-solid fa-location-dot"></i> {{ $address }}</span>
                @endif
                <a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i> {{ $email }}</a>
                <span class="dn-socials"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-pinterest-p"></i></span>
                <div class="dn-auth-actions">
                    @auth('admin')
                        <a href="{{ route('admin.index') }}">Quản trị</a>
                    @elseif(auth('customer')->check())
                        <a href="{{ route('customer.account') }}">Tài khoản</a>
                    @else
                        <button type="button" data-dn-auth-open="login">Đăng nhập</button>
                        <span aria-hidden="true">/</span>
                        <button type="button" data-dn-auth-open="register">Đăng ký</button>
                    @endauth
                </div>
            </div>
            <div class="dn-navbar">
                <button class="dn-menu-toggle" type="button" data-dn-menu aria-expanded="false" aria-controls="dn-main-menu"><i class="fa-solid fa-bars"></i><span>Menu</span></button>
                <nav id="dn-main-menu" data-dn-nav>
                    @foreach($menuItems as $item)
                        @include('theme-dn302::partials.menu-item', ['item' => $item, 'level' => 0])
                    @endforeach
                    @guest('customer')
                        @guest('admin')
                            <span class="dn-auth-mobile">
                                <button type="button" data-dn-auth-open="login">Đăng nhập</button>
                                <button type="button" data-dn-auth-open="register">Đăng ký</button>
                            </span>
                        @endguest
                    @endguest
                </nav>
                <a class="dn-consult" href="{{ route('site.contact') }}" data-dn-consult-open>@themeT('DN302.header.consultation', 'Đăng ký tư vấn')</a>
            </div>
        </div>
    </div>
</header>
