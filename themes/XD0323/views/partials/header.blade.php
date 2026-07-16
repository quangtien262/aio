@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD0323 Construction'))) ?: 'XD0323 Construction';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '1900 6750')) ?: '1900 6750';
    $email = trim((string) ($branding['support_email'] ?? 'support@XD0323.vn')) ?: 'support@XD0323.vn';
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0323', app()->getLocale(), $key);
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#'), 'children' => $item['children'] ?? []])
        ->values();
    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => $themeText('XD0323.nav.home'), 'href' => route('site.home'), 'children' => []],
            ['label' => $themeText('XD0323.nav.services'), 'href' => '#dich-vu', 'children' => []],
            ['label' => $themeText('XD0323.nav.story'), 'href' => '#gioi-thieu', 'children' => []],
            ['label' => $themeText('XD0323.nav.menu'), 'href' => '#du-an', 'children' => []],
            ['label' => $themeText('XD0323.nav.news'), 'href' => '#tin-tuc', 'children' => []],
            ['label' => $themeText('XD0323.nav.team'), 'href' => '#doi-ngu', 'children' => []],
        ]);
    }
@endphp
<header class="foot-header">
    <div class="foot-header__masthead">
        <div class="foot-container foot-header__masthead-inner">
            <div class="c323-header-contact"><a href="tel:{{ preg_replace('/[^0-9]+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> {{ $hotline }}</a><a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i> {{ $email }}</a></div>
            <a class="foot-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if ($logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="foot-brand__monogram">N</span>
                    <span><strong>{{ $companyName }}</strong><small>@themeT('XD0323.brand.tagline')</small></span>
                @endif
            </a>
            <div class="foot-header__account">
                @guest('customer')
                    <button type="button" data-xd-auth-open="login">@themeT('XD0323.header.login')</button>
                    <span aria-hidden="true">/</span>
                    <button type="button" data-xd-auth-open="register">@themeT('XD0323.header.register')</button>
                @else
                    <a href="{{ route('customer.account') }}">@themeT('XD0323.header.account')</a>
                @endguest
            </div>
        </div>
    </div>
    <div class="foot-container foot-navigation-wrap">
        <button type="button" class="foot-mobile-toggle" data-foot-menu-toggle aria-expanded="false" aria-label="@themeT('XD0323.header.open_menu')">Menu</button>
        <nav class="foot-navigation" data-foot-menu aria-label="@themeT('XD0323.header.primary_nav')">
            @foreach ($navItems as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
