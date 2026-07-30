@php
    $profile = $siteProfile ?? [];
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($profile, 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'ND Interior'))) ?: 'ND Interior';
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec15-header" data-ec15-header>
    <div class="ec15-container ec15-header-inner">
        <a class="ec15-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>ND</span><b>Interior</b>@endif
        </a>
        <nav data-ec15-nav>
            @foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach
        </nav>
        <div class="ec15-header-actions">
            <a href="{{ route('site.catalog.search') }}"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#yeu-thich"><i class="fa-regular fa-heart"></i></a>
            @guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-circle-user"></i></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-circle-user"></i></a>@endguest
            <a class="ec15-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><em>{{ (int) data_get($cart ?? [], 'count', 0) }}</em></a>
            <button class="ec15-menu-toggle" type="button" data-ec15-menu><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
