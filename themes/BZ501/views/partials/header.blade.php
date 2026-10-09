@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Halufin'))) ?: 'Halufin';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $location = trim((string) ($branding['support_location'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
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
    $menuLocales = \App\Support\FrontendLocalization::supportedLocales();
    $menuPath = static function (string $url) use ($menuLocales): string {
        $segments = explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'));
        if (in_array($segments[0] ?? '', $menuLocales, true)) {
            array_shift($segments);
        }

        return trim(implode('/', $segments), '/');
    };
    $currentMenuPath = $menuPath(request()->getPathInfo());
    $menuFamilies = [
        array_merge(\App\Support\FrontendLocalization::segmentValues('search'), \App\Support\FrontendLocalization::segmentValues('category'), \App\Support\FrontendLocalization::segmentValues('product')),
        ['s', 'ser'],
        ['pj', 'prj'],
        ['c', 'n', 'news-categories', 'topics', 'tags'],
    ];
    $menuMatchScore = function (array $item) use (&$menuMatchScore, $menuPath, $currentMenuPath, $menuFamilies): int {
        $href = trim((string) ($item['href'] ?? $item['url'] ?? ''));
        $score = 0;
        $host = parse_url($href, PHP_URL_HOST);
        if ($href !== '' && !str_starts_with($href, '#') && !parse_url($href, PHP_URL_FRAGMENT)
            && (!$host || $host === request()->getHost())) {
            $path = $menuPath($href);
            if ($path === $currentMenuPath) {
                $score = 1000 + strlen($path);
            } elseif ($path !== '' && str_starts_with($currentMenuPath, $path.'/')) {
                $score = 500 + strlen($path);
            } else {
                $menuSegment = explode('/', $path)[0];
                $currentSegment = explode('/', $currentMenuPath)[0];
                foreach ($menuFamilies as $family) {
                    if (in_array($menuSegment, $family, true) && in_array($currentSegment, $family, true)) {
                        $score = 100;
                        break;
                    }
                }
            }
        }
        foreach ($item['children'] ?? [] as $child) {
            if (is_array($child)) {
                $score = max($score, $menuMatchScore($child));
            }
        }

        return $score;
    };
    $activeMenuIndex = null;
    $bestMenuScore = 0;
    foreach ($navItems as $index => $item) {
        $score = $menuMatchScore($item);
        if ($score > $bestMenuScore) {
            $bestMenuScore = $score;
            $activeMenuIndex = $index;
        }
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
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>

        <button type="button" class="bz501-menu-toggle" data-bz501-menu-toggle aria-expanded="false" aria-label="@themeT('BZ501.header.open_menu')">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav class="bz501-navigation" data-bz501-menu aria-label="@themeT('BZ501.header.primary_nav')">
            @foreach ($navItems as $index => $item)
                <a href="{{ $item['href'] }}" @class(['is-active' => $index === $activeMenuIndex]) @if($index === $activeMenuIndex) aria-current="page" @endif>{{ $item['label'] }}</a>
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
                <a class="bz501-admin-link" href="{{ route('admin.index') }}" target="_blank" rel="noopener">Admin</a>
            @endif

            @include('partials.storefront-language-switcher')
        </div>
    </div>
</header>
