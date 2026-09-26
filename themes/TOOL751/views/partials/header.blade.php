@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'BeeTools Store'))) ?: 'BeeTools Store';
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $location = trim((string) data_get($branding, 'support_location', ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="t751-header">
    <div class="t751-info"><div class="t751-container">
        <a class="t751-logo" href="{{ $homeUrl }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span class="t751-logo-mark">B</span><span><b>{{ $company }}</b><small>{{ data_get($branding, 'slogan', __('Dụng cụ cơ khí cho mọi nhà')) }}</small></span>@endif
        </a>
        <div class="t751-contact"><i class="fa-solid fa-location-dot"></i><span><small>@themeT('footer.contact', 'Địa chỉ')</small><b>{{ $location ?: '266 Đội Cấn, Ba Đình, Hà Nội' }}</b></span></div>
        <div class="t751-contact"><i class="fa-solid fa-phone-volume"></i><span><small>@themeT('contact', 'Hỗ trợ mua hàng')</small><b>{{ $hotline ?: '1900 6750' }}</b></span></div>
        <div class="t751-contact t751-warranty"><i class="fa-solid fa-phone-volume"></i><span><small>{{ __('Hỗ trợ bảo hành') }}</small><b>{{ $hotline ?: '1900 6750' }}</b></span></div>
        <a class="t751-cart" href="{{ route('site.cart.index', ['locale' => app()->getLocale()]) }}"><i class="fa-solid fa-cart-shopping"></i><span><b>@themeT('cart', 'Giỏ hàng')</b><small>({{ (int) ($cartCount ?? 0) }}) {{ __('sản phẩm') }}</small></span></a>

            @include('partials.storefront-language-switcher')
        </div></div>
    <div class="t751-navbar"><div class="t751-container">
        <button type="button" class="t751-categories" data-t751-menu><i class="fa-solid fa-bars"></i>{{ __('Danh mục sản phẩm') }}</button>
        <nav data-t751-nav>
            @forelse($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@empty
                <a href="{{ $homeUrl }}">@themeT('home', 'Trang chủ')</a><a href="#gioi-thieu">@themeT('about', 'Giới thiệu')</a><a href="#san-pham">@themeT('products', 'Sản phẩm')</a><a href="#tin-tuc">@themeT('news', 'Tin tức')</a><a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('contact', 'Liên hệ')</a>
            @endforelse
        </nav>
        <form class="t751-search" action="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}"><input name="q" placeholder="@themeT('search.placeholder', 'Tìm kiếm sản phẩm...')"><button aria-label="@themeT('search', 'Tìm kiếm')"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        @guest('customer')<button class="t751-account" type="button" data-xd-auth-open="login" aria-label="@themeT('auth.login', 'Đăng nhập')"><i class="fa-regular fa-user"></i></button>@else<a class="t751-account" href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}"><i class="fa-regular fa-user"></i></a>@endguest
    </div></div>
</header>
