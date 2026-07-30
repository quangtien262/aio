@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $cartCount = (int) data_get($cartSummary ?? [], 'count', 0);
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="book20-header">
    <div class="book20-topbar">
        <div class="book20-container"><span><i class="fa-solid fa-phone"></i> {{ data_get($branding, 'support_hotline', '') }}</span><span><i class="fa-regular fa-envelope"></i> {{ data_get($branding, 'support_email', '') }}</span><form action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Nhập từ khóa..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form></div>
    </div>
    <div class="book20-container book20-header-main">
        <a class="book20-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'Bookle') }}">@endif
        </a>
        <button class="book20-menu-toggle" data-book20-menu aria-label="Mở menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <nav data-book20-nav>
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </nav>
        <div class="book20-actions"><button data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button><a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a></div>
    </div>
</header>
@include('partials.storefront-language-switcher')
