@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Halufin'))) ?: 'Halufin';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $location = trim((string) ($branding['support_location'] ?? '')) ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.header.location');
    $email = trim((string) ($branding['support_email'] ?? '')) ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.header.email');
    $hotline = trim((string) ($branding['support_hotline'] ?? '')) ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.header.hotline');
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => [
            'label' => (string) ($item['label'] ?? $item['title']),
            'href' => (string) ($item['url'] ?? $item['href'] ?? '#'),
            'children' => $item['children'] ?? [],
        ])
        ->values();

    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.nav.home'), 'href' => route('site.home'), 'children' => []],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.nav.about'), 'href' => '#gioi-thieu', 'children' => []],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.nav.products'), 'href' => '#san-pham', 'children' => []],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.nav.news'), 'href' => '#tin-tuc', 'children' => []],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BZ501', app()->getLocale(), 'BZ501.nav.contact'), 'href' => '#footer', 'children' => []],
        ]);
    }
@endphp

<header class="bz501-header">
    <div class="bz501-topbar">
        <div class="bz501-container bz501-topbar__inner">
            <span><i class="fa-solid fa-location-dot"></i>{{ $location }}</span>
            <span><i class="fa-regular fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></span>
            <span><i class="fa-solid fa-phone"></i><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></span>
            <div class="bz501-social" aria-label="Social">
                <a href="#footer" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                <a href="#footer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#footer" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a>
                <a href="#footer" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                <a href="#footer" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>
    </div>

    <div class="bz501-container bz501-navrow">
        <a class="bz501-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if ($logoUrl !== '')
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="bz501-brand__mark"><i class="fa-solid fa-chart-line"></i></span>
                <strong>{{ $companyName }}</strong>
            @endif
        </a>

        <button type="button" class="bz501-menu-toggle" data-bz501-menu-toggle aria-expanded="false" aria-label="@themeT('BZ501.header.open_menu')">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav class="bz501-navigation" data-bz501-menu aria-label="@themeT('BZ501.header.primary_nav')">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="bz501-actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></a>
            @guest('customer')
                <button type="button" data-xd-auth-open="login" aria-label="@themeT('BZ501.header.login')"><i class="fa-regular fa-user"></i></button>
            @else
                <a href="{{ route('customer.account') }}" aria-label="@themeT('BZ501.header.account')"><i class="fa-regular fa-user"></i></a>
            @endguest
            <a class="bz501-cart" href="{{ route('site.cart.index') }}" aria-label="Cart"><i class="fa-solid fa-cart-shopping"></i><span>0</span></a>
            @if (auth('admin')->check())
                <a class="bz501-admin-link" href="{{ url('/admin') }}" target="_blank" rel="noopener">Admin</a>
            @endif
        </div>
    </div>
</header>
