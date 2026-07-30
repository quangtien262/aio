@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SHOP601', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', ''))) ?: $t('SHOP601.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) $nav = collect([
        ['label' => $t('SHOP601.nav.home'), 'url' => route('site.home')], ['label' => $t('SHOP601.nav.about'), 'url' => '#bo-suu-tap'], ['label' => $t('SHOP601.nav.products'), 'url' => route('site.catalog.search')], ['label' => 'Mix & Match', 'url' => '#bo-suu-tap'], ['label' => $t('SHOP601.nav.feedback'), 'url' => '#danh-gia'], ['label' => $t('SHOP601.nav.news'), 'url' => route('site.blog.index')], ['label' => $t('SHOP601.nav.contact'), 'url' => route('site.contact')],
    ]);
@endphp
<header class="s601-header">
    <div class="s601-header-top"><div class="s601-container s601-header-top__inner">
        <a class="s601-logo" href="{{ route('site.home') }}">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $name }}">
            @else
                <span>BEAN</span> Style
            @endif
        </a>
        <form class="s601-search" action="{{ route('site.catalog.search') }}" method="GET"><input name="q" type="search" placeholder="{{ $t('SHOP601.header.search') }}"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="s601-head-actions"><a href="#footer"><i class="fa-solid fa-location-dot"></i><span>{{ $t('SHOP601.header.stores') }}</span></a>@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>{{ $t('SHOP601.header.account') }}<b>{{ $t('SHOP601.header.login') }}</b></span></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>{{ $t('SHOP601.header.account') }}</span></a>@endguest<a href="#danh-gia" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><em>0</em></a><a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><em>0</em></a></div>
    </div></div>
    <div class="s601-navrow"><div class="s601-container s601-navrow__inner">
        <button class="s601-menu-toggle" type="button" data-s601-menu-toggle><i class="fa-solid fa-bars"></i><span>{{ $t('SHOP601.header.categories') }}</span></button>
        <nav data-s601-nav>@foreach($nav as $item)<a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</nav>
        <div class="s601-promos"><span>• {{ $t('SHOP601.header.live') }}</span><b>🎁 {{ $t('SHOP601.header.promotion') }}</b></div>
    </div></div>
</header>
@include('partials.storefront-language-switcher')
