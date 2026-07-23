@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'WolfArch'))) ?: 'WolfArch';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $locale = app()->getLocale();
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0324', $locale, $key);
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#')])
        ->values();

    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => $themeText('XD0324.nav.home'), 'href' => route('site.home')],
            ['label' => $themeText('XD0324.nav.about'), 'href' => '#gioi-thieu'],
            ['label' => $themeText('XD0324.nav.news'), 'href' => '#tin-tuc'],
            ['label' => $themeText('XD0324.nav.projects'), 'href' => '#du-an'],
            ['label' => $themeText('XD0324.nav.products'), 'href' => '#san-pham'],
            ['label' => $themeText('XD0324.nav.services'), 'href' => '#dich-vu'],
            ['label' => $themeText('XD0324.nav.trends'), 'href' => '#xu-huong'],
            ['label' => $themeText('XD0324.nav.contact'), 'href' => '#lien-he'],
        ]);
    }
@endphp
<header class="xd324-header" data-xd324-header>
    <div class="xd324-container xd324-header__inner">
        <a class="xd324-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if ($logoUrl !== '')
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span>WolfArch</span>
            @endif
        </a>
        <button type="button" class="xd324-menu-toggle" data-foot-menu-toggle aria-expanded="false" aria-label="{{ $themeText('XD0324.header.open_menu') }}"><i class="fa-solid fa-bars"></i></button>
        <nav class="xd324-nav" data-foot-menu aria-label="{{ $themeText('XD0324.header.primary_nav') }}">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <div class="xd324-actions">
            @if (auth('admin')->check())
                <a class="xd324-admin" href="{{ route('admin.index') }}" target="_blank" rel="noopener">{{ $themeText('XD0324.header.admin') }}</a>
            @endif
            <a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            @guest('customer')
                <button type="button" data-xd-auth-open="login" aria-label="{{ $themeText('XD0324.header.login') }}"><i class="fa-regular fa-user"></i></button>
            @else
                <a href="{{ route('customer.account') }}" aria-label="{{ $themeText('XD0324.header.account') }}"><i class="fa-regular fa-user"></i></a>
            @endguest
            <a class="xd324-badge" href="#yeu-thich" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><span>0</span></a>
            <a class="xd324-badge xd324-badge--cart" href="{{ route('site.cart.index') }}" aria-label="{{ $themeText('XD0324.header.cart') }}"><i class="fa-solid fa-cart-shopping"></i><span>0</span></a>
        </div>
    </div>
</header>
