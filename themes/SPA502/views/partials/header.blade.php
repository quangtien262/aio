@php
    $branding = $siteProfile['branding'] ?? [];
    $companyName = $siteProfile['site_name'] ?? 'HALU';
    $logoUrl = $branding['logo_url'] ?? $siteProfile['logo_url'] ?? null;
    $supportEmail = $siteProfile['support_email'] ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.header.email');
    $hotline = $siteProfile['hotline'] ?? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.header.hotline');
    $cartCount = (int) ($cartCount ?? session('cart_count', 0));
    $wishlistCount = (int) ($wishlistCount ?? 0);
    $compareCount = (int) ($compareCount ?? 0);
    $fallbackMenu = [
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.nav.home'), 'href' => route('site.home')],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.nav.about'), 'href' => '#gioi-thieu'],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.nav.products'), 'href' => route('site.catalog.search')],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.nav.news'), 'href' => route('site.blog.index')],
        ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SPA502', app()->getLocale(), 'SPA502.nav.contact'), 'href' => route('site.contact')],
    ];
    $menuItems = collect($landingMenuItems ?? $menus['primary-navigation'] ?? $menus['primary'] ?? $fallbackMenu)
        ->map(fn ($item) => is_array($item) ? $item : [])
        ->filter(fn ($item) => filled($item['label'] ?? $item['title'] ?? null))
        ->values();
@endphp

<header class="spa502-header">
    <div class="spa502-topline"></div>
    <div class="spa502-topbar">
        <div class="spa502-container spa502-topbar__inner">
            <span><i class="fa-solid fa-location-dot"></i> @themeT('SPA502.header.store_system')</span>
            <span class="spa502-topbar__spacer"></span>
            <a href="mailto:{{ $supportEmail }}"><i class="fa-regular fa-envelope"></i> {{ $supportEmail }}</a>
            <i class="spa502-divider"></i>
            <a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> {{ $hotline }}</a>
        </div>
    </div>
    <div class="spa502-navrow">
        <div class="spa502-container spa502-navrow__inner">
            <a class="spa502-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if(filled($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="spa502-brand__mark"><i class="fa-solid fa-flask"></i></span>
                    <span><strong>HALU</strong><small>@themeT('SPA502.brand.tagline')</small></span>
                @endif
            </a>

            <button class="spa502-menu-toggle" type="button" data-spa502-menu-toggle aria-label="@themeT('SPA502.header.open_menu')">
                <i class="fa-solid fa-bars"></i>
            </button>

            <nav class="spa502-navigation" data-spa502-menu>
                @foreach($menuItems as $item)
                    <a href="{{ $item['href'] ?? $item['url'] ?? '#' }}">{{ $item['label'] ?? $item['title'] }}</a>
                @endforeach
            </nav>

            <div class="spa502-actions">
                <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('SPA502.header.search')"><i class="fa-solid fa-magnifying-glass"></i></a>
                @auth('customer')
                    <a href="{{ route('customer.account') }}" aria-label="@themeT('SPA502.header.account')"><i class="fa-regular fa-user"></i></a>
                @else
                    <button type="button" data-xd-auth-open="login" aria-label="@themeT('SPA502.header.account')"><i class="fa-regular fa-user"></i></button>
                @endauth
                <a class="spa502-badge" href="#" aria-label="@themeT('SPA502.header.wishlist')"><i class="fa-regular fa-heart"></i><span>{{ $wishlistCount }}</span></a>
                <a class="spa502-badge" href="#" aria-label="@themeT('SPA502.header.compare')"><i class="fa-solid fa-rotate"></i><span>{{ $compareCount }}</span></a>
                <a class="spa502-badge" href="{{ route('site.cart.index') }}" aria-label="@themeT('SPA502.header.cart')"><i class="fa-solid fa-basket-shopping"></i><span>{{ $cartCount }}</span></a>
            </div>
        </div>
    </div>
</header>
