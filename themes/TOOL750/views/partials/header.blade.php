@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'TOOL750'))) ?: 'TOOL750';
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))
        ->values();
@endphp
<header class="t750-header">
    <div class="t750-utility">
        <div class="t750-container">
            <span>{{ data_get($branding, 'slogan', __('Chào mừng bạn đến với thế giới thiết bị cơ khí chuyên nghiệp!')) }}</span>
            <div>
                @if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif
                @if($email)<a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i>{{ $email }}</a>@endif
            </div>
        </div>
    </div>
    <div class="t750-nav-shell">
        <div class="t750-brand-wedge">
            <a class="t750-logo" href="{{ $homeUrl }}">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $company }}">
                @else
                    <span class="t750-mark"><i class="fa-solid fa-gears"></i></span>
                    <span><b>{{ $company }}</b><small>{{ data_get($branding, 'slogan', __('Thiết bị cho mọi nhà')) }}</small></span>
                @endif
            </a>
        </div>
        <div class="t750-nav-main">
            <button class="t750-menu-toggle" type="button" data-t750-menu aria-label="@themeT('menu.open', 'Mở menu')"><i class="fa-solid fa-bars"></i></button>
            <nav data-t750-nav>
                @forelse($nav as $item)
                    <a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>
                @empty
                    <a href="{{ $homeUrl }}">@themeT('home', 'Trang chủ')</a>
                    <a href="#gioi-thieu">@themeT('about', 'Giới thiệu')</a>
                    <a href="#san-pham">@themeT('products', 'Sản phẩm')</a>
                    <a href="#tin-tuc">@themeT('news', 'Tin tức')</a>
                    <a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('contact', 'Liên hệ')</a>
                @endforelse
            </nav>
            <div class="t750-actions">
                <button type="button" data-t750-search aria-label="@themeT('search', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i></button>
                @guest('customer')
                    <button type="button" data-xd-auth-open="login" aria-label="@themeT('auth.login', 'Đăng nhập')"><i class="fa-regular fa-user"></i></button>
                @else
                    <a href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}" aria-label="@themeT('auth.account', 'Tài khoản')"><i class="fa-regular fa-user"></i></a>
                @endguest
                <a class="t750-cart" href="{{ route('site.cart.index', ['locale' => app()->getLocale()]) }}" aria-label="@themeT('cart', 'Giỏ hàng')"><i class="fa-solid fa-bag-shopping"></i><span>{{ (int) ($cartCount ?? 0) }}</span></a>

            @include('partials.storefront-language-switcher')
        </div>
        </div>
    </div>
    <div class="t750-search-panel" data-t750-search-panel>
        <form action="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}">
            <input name="q" autocomplete="off" placeholder="@themeT('search.placeholder', 'Tìm máy móc, dụng cụ, phụ kiện...')">
            <button aria-label="@themeT('search', 'Tìm kiếm')"><i class="fa-solid fa-arrow-right"></i></button>
        </form>
    </div>
</header>
