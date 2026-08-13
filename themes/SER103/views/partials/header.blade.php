@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'SER103');
    $logoUrl = data_get($branding, 'logo_url') ?: data_get($siteProfile ?? [], 'logo_url');
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)
        ->bladeText('SER103', app()->getLocale(), $key);
    $fallbackMenu = [
        ['label' => $themeText('SER103.nav.home'), 'href' => route('site.home')],
        ['label' => $themeText('SER103.nav.services'), 'href' => '#dich-vu'],
        ['label' => $themeText('SER103.nav.pricing'), 'href' => '#bang-gia'],
        ['label' => $themeText('SER103.nav.products'), 'href' => '#san-pham'],
        ['label' => $themeText('SER103.nav.news'), 'href' => '#tin-tuc'],
        ['label' => $themeText('SER103.nav.contact'), 'href' => '#lien-he'],
    ];
    $menuItems = collect(data_get($menus ?? [], 'primary-navigation') ?? data_get($menus ?? [], 'primary') ?? $landingMenuItems ?? $fallbackMenu)
        ->map(fn ($item) => is_array($item) ? $item : [])
        ->filter(fn ($item) => filled($item['label'] ?? $item['title'] ?? null))
        ->values();
@endphp
<header class="ser103-header" data-ser103-header>
    <div class="ser103-container ser103-header__inner">
        <a class="ser103-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if(filled($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @endif
        </a>
        <button class="ser103-menu-toggle" type="button" data-ser103-menu-toggle aria-label="@themeT('SER103.header.open_menu')">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="ser103-nav" data-ser103-menu>
            @foreach($menuItems as $item)
                <a href="{{ $item['href'] ?? $item['url'] ?? '#' }}">{{ $item['label'] ?? $item['title'] }}</a>
            @endforeach
        </nav>
        <div class="ser103-header__actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="@themeT('SER103.header.search')"><i class="fa-solid fa-magnifying-glass"></i></a>
            <button class="ser103-booking-button" type="button" data-ser103-booking-open>
                <span>@themeT('SER103.header.booking')</span><i class="fa-solid fa-arrow-right-long"></i>
            </button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
