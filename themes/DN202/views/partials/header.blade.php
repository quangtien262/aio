@php
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('DN202', app()->getLocale(), $key);
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = $branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'DN202 Arc');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $address = $branding['support_location'] ?? '';
    $hotline = $branding['support_hotline'] ?? '';
    $hours = $branding['working_hours'] ?? '08:00 - 17:00';
    $menu = collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($menu->isEmpty()) {
        $menu = collect([
            ['label' => $t('nav.home'), 'url' => route('site.home')],
            ['label' => $t('nav.products'), 'url' => route('site.catalog.search')],
            ['label' => $t('nav.projects'), 'url' => route('site.projects.index')],
            ['label' => $t('nav.villas'), 'url' => route('site.home').'#thiet-ke-biet-thu'],
            ['label' => $t('nav.news'), 'url' => route('site.blog.index')],
            ['label' => $t('nav.about'), 'url' => route('site.home').'#dich-vu'],
            ['label' => $t('nav.contact'), 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="d202-header">
    <div class="d202-topbar">
        <div class="d202-container">
            <span class="d202-welcome"><i class="fa-solid fa-circle"></i>{{ $t('header.welcome') }}</span>
            <div class="d202-top-actions">
                <form action="{{ route('site.catalog.search') }}"><input name="q" placeholder="{{ $t('header.search') }}"><button aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button></form>
                <button type="button" data-d202-auth-open><i class="fa-regular fa-circle-user"></i> {{ $t('header.account') }}</button>
                <a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i> {{ $t('header.cart') }}</a>
            </div>
        </div>
    </div>
    <div class="d202-brandbar d202-container">
        <a class="d202-logo" href="{{ route('site.home') }}" aria-label="{{ $company }}">
            @if($logo !== '')
                <img src="{{ $logo }}" alt="{{ $company }}">@endif
        </a>
        <div class="d202-contact-points">
            <div><i class="fa-solid fa-location-dot"></i><span><strong>{{ $t('header.office') }}</strong><small>{{ $address }}</small></span></div>
            <div><i class="fa-regular fa-clock"></i><span><strong>{{ $t('header.schedule') }}</strong><small>{{ $hours }}</small></span></div>
            <div><i class="fa-solid fa-headset"></i><span><strong>{{ $t('header.consulting') }}</strong><small>{{ $t('header.call_now') }}: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">{{ $hotline }}</a></small></span></div>
        </div>
    </div>
    <div class="d202-navrow">
        <div class="d202-container">
            <button class="d202-menu-toggle" type="button" data-d202-menu-toggle aria-label="Menu"><span>MENU</span><i class="fa-solid fa-bars-staggered"></i></button>
            <nav data-d202-menu>@foreach($menu as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@endforeach</nav>
            <a class="d202-nav-cta" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}"><i class="fa-solid fa-phone-volume"></i><span>{{ $t('header.consulting') }}</span></a>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
