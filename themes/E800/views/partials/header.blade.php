@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('E800', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', ''))) ?: $t('E800.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) $nav = collect([
        ['label' => $t('E800.nav.home'), 'url' => route('site.home')],
        ['label' => $t('E800.nav.products'), 'url' => route('site.catalog.search')],
        ['label' => $t('E800.nav.promotions'), 'url' => route('site.blog.index')],
        ['label' => $t('E800.nav.blog'), 'url' => route('site.blog.index')],
        ['label' => $t('E800.nav.guide'), 'url' => route('site.contact')],
    ]);
@endphp
<header class="e800-header" data-e800-header>
    <div class="e800-container e800-header__inner">
        <a class="e800-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span>{{ $name }}</span>@endif
        </a>
        <button class="e800-menu-toggle" type="button" data-e800-menu-toggle aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="e800-nav" data-e800-nav>@foreach($nav as $item)<a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</nav>
        <div class="e800-actions">
            <a class="e800-live" href="{{ route('site.blog.index') }}"><i></i>{{ $t('E800.header.live') }}</a>
            <button type="button" data-e800-search-open aria-label="{{ $t('E800.header.search') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="{{ $t('E800.header.account') }}"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}" aria-label="{{ $t('E800.header.account') }}"><i class="fa-regular fa-user"></i></a>@endguest
            <a class="e800-cart" href="{{ route('site.cart.index') }}" aria-label="{{ $t('E800.header.cart') }}"><i class="fa-solid fa-cart-shopping"></i><em>{{ (int) data_get($shell, 'cart_count', 0) }}</em></a>
        </div>
    </div>
    <form class="e800-search-panel" action="{{ route('site.catalog.search') }}" method="GET" data-e800-search hidden><div class="e800-container"><input name="q" type="search" placeholder="{{ $t('E800.header.search') }}..." autofocus><button aria-label="{{ $t('E800.header.search') }}"><i class="fa-solid fa-arrow-right"></i></button></div></form>
</header>
@include('partials.storefront-language-switcher')
