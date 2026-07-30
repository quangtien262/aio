@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'FOOT401 Restaurant'))) ?: 'FOOT401 Restaurant';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('FOOT401', app()->getLocale(), $key);
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#'), 'children' => $item['children'] ?? []])
        ->values();
    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => $themeText('foot401.nav.home'), 'href' => route('site.home'), 'children' => []],
            ['label' => $themeText('foot401.nav.services'), 'href' => '#dich-vu', 'children' => []],
            ['label' => $themeText('foot401.nav.story'), 'href' => '#gioi-thieu', 'children' => []],
            ['label' => $themeText('foot401.nav.menu'), 'href' => '#thuc-don', 'children' => []],
            ['label' => $themeText('foot401.nav.news'), 'href' => '#tin-tuc', 'children' => []],
            ['label' => $themeText('foot401.nav.team'), 'href' => '#doi-ngu', 'children' => []],
        ]);
    }
@endphp
<header class="foot-header">
    <div class="foot-header__masthead">
        <div class="foot-container foot-header__masthead-inner">
            <a class="foot-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if ($logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="foot-brand__monogram">F</span>
                    <span><strong>{{ $companyName }}</strong><small>@themeT('foot401.brand.tagline')</small></span>
                @endif
            </a>
            <div class="foot-header__account">
                @guest('customer')
                    <button type="button" data-xd-auth-open="login">@themeT('foot401.header.login')</button>
                    <span aria-hidden="true">/</span>
                    <button type="button" data-xd-auth-open="register">@themeT('foot401.header.register')</button>
                @else
                    <a href="{{ route('customer.account') }}">@themeT('foot401.header.account')</a>
                @endguest
            </div>
        </div>
    </div>
    <div class="foot-container foot-navigation-wrap">
        <button type="button" class="foot-mobile-toggle" data-foot-menu-toggle aria-expanded="false" aria-label="@themeT('foot401.header.open_menu')">Menu</button>
        <nav class="foot-navigation" data-foot-menu aria-label="@themeT('foot401.header.primary_nav')">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
@include('partials.storefront-language-switcher')
