@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'SER102');
    $logoUrl = data_get($branding, 'logo_url') ?: data_get($siteProfile ?? [], 'logo_url');
    $cartCount = (int) ($cartCount ?? collect(session('storefront_cart.items', []))->sum('quantity'));
    $fallbackMenu = [
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.home'), 'href' => route('site.home')],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.services'), 'href' => '#dich-vu'],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.pricing'), 'href' => '#bang-gia'],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.products'), 'href' => '#san-pham'],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.news'), 'href' => '#tin-tuc'],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SER102', app()->getLocale(), 'SER102.nav.contact'), 'href' => '#lien-he'],
    ];
    $menuItems = collect($landingMenuItems ?? data_get($menus ?? [], 'primary-navigation') ?? data_get($menus ?? [], 'primary') ?? $fallbackMenu)
        ->map(fn ($item) => is_array($item) ? $item : [])
        ->filter(fn ($item) => filled($item['label'] ?? $item['title'] ?? null))
        ->values();
@endphp

<header class="ser102-header" data-ser102-header>
    <div class="ser102-container ser102-header__inner">
        <a class="ser102-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if(filled($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>
        <button class="ser102-menu-toggle" type="button" data-ser102-menu-toggle aria-label="@themeT('SER102.header.open_menu')">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="ser102-nav" data-ser102-menu>
            @foreach($menuItems as $item)
                <a href="{{ $item['href'] ?? $item['url'] ?? '#' }}">{{ $item['label'] ?? $item['title'] }}</a>
            @endforeach
        </nav>
        <div class="ser102-header__actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('SER102.header.search')"><i class="fa-solid fa-magnifying-glass"></i></a>
            @auth('customer')
                <a href="{{ route('customer.account') }}" aria-label="@themeT('SER102.header.account')"><i class="fa-regular fa-user"></i></a>
            @else
                <button type="button" data-xd-auth-open="login" aria-label="@themeT('SER102.header.account')"><i class="fa-regular fa-user"></i></button>
            @endauth
            <a class="ser102-cart" href="{{ route('site.cart.index') }}" aria-label="@themeT('SER102.header.cart')"><i class="fa-solid fa-cart-shopping"></i><span>{{ $cartCount }}</span></a>
            <button class="ser102-booking-button" type="button" data-ser102-booking-open><i class="fa-regular fa-calendar-check"></i><span>@themeT('SER102.header.booking')</span></button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
