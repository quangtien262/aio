@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $hotline = $branding['support_hotline'] ?? '1900 9477';
    $email = $branding['support_email'] ?? data_get($siteProfile ?? [], 'email', 'admin@demo.web30s.vn');
    $logo = data_get($siteProfile ?? [], 'logo_url', data_get($siteProfile ?? [], 'logo'));
    $menuItems = collect(data_get($primaryMenu ?? [], 'items', []));
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
        <a class="dn-logo" href="{{ route('site.home') }}" aria-label="Janelas - Trang chủ">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'Janelas') }}">
            @else
                <i class="fa-regular fa-window-maximize"></i>
                <span>janelas<small>Windows &amp; Doors</small></span>
            @endif
        </a>
        <div class="dn-head-main">
            <div class="dn-topbar">
                <span><i class="fa-solid fa-location-dot"></i> @themeT('DN302.header.address', '344 Huỳnh Tấn Phát, Phường Bình Thuận, Quận 7, TP.HCM')</span>
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
                        <a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label', data_get($item, 'title')) }}</a>
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
                <a class="dn-consult" href="{{ route('site.contact') }}">@themeT('DN302.header.consultation', 'Đăng ký tư vấn')</a>
            </div>
        </div>
    </div>
</header>
