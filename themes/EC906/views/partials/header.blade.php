@php
    $profile = (array) ($siteProfile ?? []);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = data_get($branding, 'logo_url');
    $siteName = data_get($profile, 'site_name', 'EGA Mini Mart');
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
    $productMenu = collect(data_get($shell, 'product_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec96-header" id="top">
    <div class="ec96-container ec96-head-main">
        <button class="ec96-category-toggle" type="button" data-ec96-menu aria-expanded="false" aria-controls="ec96-category-panel" aria-label="@themeT('category_menu.title', 'Danh mục sản phẩm')"><i class="fa-solid fa-bars" aria-hidden="true"></i><span>@themeT('category_menu.title', 'Danh mục sản phẩm')</span></button>
        <a class="ec96-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif
        </a>
        <div class="ec96-head-actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>@endguest
            <a class="ec96-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em><span>Giỏ hàng</span></a>
        </div>
    </div>
    <nav class="ec96-nav" data-ec96-nav><div class="ec96-container">
        @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
    </div></nav>
    <div class="ec96-category-panel" id="ec96-category-panel" data-ec96-categories hidden>
        <nav aria-label="@themeT('category_menu.title', 'Danh mục sản phẩm')">
            <a class="ec96-category-all" href="{{ route('site.catalog.search') }}">@themeT('category_menu.all', 'Tất cả sản phẩm') <span aria-hidden="true">→</span></a>
            @include('theme-ec906::partials.category-menu', ['categoryMenuItems' => $productMenu])
        </nav>
        @if($nav->isNotEmpty())<nav class="ec96-category-mobile-nav" aria-label="@themeT('category_menu.navigation', 'Điều hướng website')">@foreach($nav as $item)<a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] }}</a>@endforeach</nav>@endif
    </div>
</header>
@include('partials.storefront-language-switcher')
