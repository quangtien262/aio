@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Euro Farm'))) ?: 'Euro Farm';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '1900 6750')) ?: '1900 6750';
    $email = trim((string) ($branding['support_email'] ?? 'support@htvietnam.vn')) ?: 'support@htvietnam.vn';
    $address = trim((string) ($branding['support_location'] ?? '70 Lữ Gia, Phường 15, Quận 11, TP.HCM')) ?: '70 Lữ Gia, Phường 15, Quận 11, TP.HCM';
    $locale = app()->getLocale();
    $themeText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0323', $locale, $key);
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => [
            'label' => (string) ($item['label'] ?? $item['title']),
            'href' => (string) ($item['url'] ?? $item['href'] ?? '#'),
            'children' => collect($item['children'] ?? [])->filter(fn ($child) => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))->values()->all(),
        ])
        ->values();

    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => $themeText('XD0323.nav.home'), 'href' => route('site.home'), 'children' => []],
            ['label' => $themeText('XD0323.nav.products'), 'href' => route('site.catalog.search'), 'children' => []],
            ['label' => $themeText('XD0323.nav.about'), 'href' => '#gioi-thieu', 'children' => []],
            ['label' => $themeText('XD0323.nav.news'), 'href' => '#tin-tuc', 'children' => []],
            ['label' => $themeText('XD0323.nav.projects'), 'href' => '#du-an', 'children' => []],
            ['label' => $themeText('XD0323.nav.team'), 'href' => '#doi-ngu', 'children' => []],
            ['label' => $themeText('XD0323.nav.faq'), 'href' => '#hoi-dap', 'children' => []],
            ['label' => $themeText('XD0323.nav.contact'), 'href' => '#lien-he', 'children' => []],
            ['label' => $themeText('XD0323.nav.distribution'), 'href' => '#dich-vu', 'children' => []],
        ]);
    }
@endphp
<header class="xd323-header">
    <div class="xd323-topbar">
        <div class="xd323-container xd323-topbar__inner">
            <strong>{{ $themeText('XD0323.brand.tagline') }}</strong>
            <div class="xd323-topbar__contact">
                <a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i>{{ $email }}</a>
                <a href="#footer"><i class="fa-solid fa-location-dot"></i>{{ $address }}</a>
            </div>
        </div>
    </div>
    <div class="xd323-masthead">
        <div class="xd323-container xd323-masthead__inner">
            <a class="xd323-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
                @if ($logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
                @else
                    <span class="xd323-brand__leaf"><i class="fa-brands fa-pagelines"></i></span>
                    <strong>{{ $companyName }}</strong>
                @endif
            </a>
            <form class="xd323-search" action="{{ route('site.catalog.search') }}" method="GET">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" name="q" placeholder="{{ $themeText('XD0323.header.search') }}">
            </form>
            <div class="xd323-call">
                <i class="fa-solid fa-phone-volume"></i>
                <span>{{ $themeText('XD0323.header.call_us') }}</span>
                <a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a>
            </div>
            <a class="xd323-shop-btn" href="{{ route('site.catalog.search') }}">{{ $themeText('XD0323.header.shop') }} <i class="fa-solid fa-arrow-right"></i></a>
            <button type="button" class="xd323-menu-toggle" data-foot-menu-toggle aria-expanded="false" aria-label="{{ $themeText('XD0323.header.open_menu') }}"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
    <div class="xd323-navrow">
        <div class="xd323-container xd323-navrow__inner">
            <nav class="xd323-nav" data-foot-menu aria-label="{{ $themeText('XD0323.header.primary_nav') }}">
                @foreach ($navItems as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="xd323-actions">
                @if (auth('admin')->check())
                    <a class="xd323-admin-link" href="{{ route('admin.index') }}" target="_blank" rel="noopener">{{ $themeText('XD0323.header.admin') }}</a>
                @endif
                @guest('customer')
                    <button type="button" data-xd-auth-open="login" aria-label="{{ $themeText('XD0323.header.login') }}"><i class="fa-regular fa-user"></i></button>
                @else
                    <a href="{{ route('customer.account') }}" aria-label="{{ $themeText('XD0323.header.account') }}"><i class="fa-regular fa-user"></i></a>
                @endguest
                <a href="#yeu-thich" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><span>0</span></a>
                <a href="{{ route('site.cart.index') }}" aria-label="{{ $themeText('XD0323.header.cart') }}"><i class="fa-solid fa-cart-shopping"></i><span>0</span></a>
            </div>
        </div>
    </div>
</header>
