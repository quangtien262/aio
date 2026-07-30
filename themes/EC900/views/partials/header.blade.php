@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC900', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? '')) ?: $t('EC900.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) {
        $nav = collect([
            ['label' => $t('EC900.nav.home'), 'url' => route('site.home')],
            ['label' => $t('EC900.nav.about'), 'url' => route('site.home').'#danh-muc-noi-bat'],
            ['label' => $t('EC900.nav.products'), 'url' => route('site.catalog.search')],
            ['label' => $t('EC900.nav.clearance'), 'url' => route('site.home').'#san-pham-ban-chay'],
            ['label' => $t('EC900.nav.warranty'), 'url' => route('site.contact')],
            ['label' => $t('EC900.nav.stores'), 'url' => route('site.contact')],
            ['label' => $t('EC900.nav.news'), 'url' => route('site.blog.index')],
        ]);
    }
@endphp
<header class="ec9-header">
    <div class="ec9-container ec9-header-main">
        <a class="ec9-logo" href="{{ route('site.home') }}">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $name }}">@endif
        </a>
        <form class="ec9-search" method="GET" action="{{ route('site.catalog.search') }}">
            <input name="q" value="{{ request('q') }}" placeholder="{{ $t('EC900.search.placeholder') }}">
            <button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="ec9-head-links">
            <a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-headset"></i><span><small>{{ $t('EC900.header.sales') }}</small><b>{{ $hotline }}</b></span></a>
            <a href="{{ route('site.contact') }}"><i class="fa-regular fa-building"></i><span><small>{{ $t('EC900.header.showroom') }}</small><b>Toàn quốc</b></span></a>
        </div>
        <div class="ec9-actions">
            @guest('customer')
                <button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button>
            @else
                <a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>
            @endguest
            <a href="#san-pham-dac-quyen" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><em>0</em></a>
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-cart-shopping"></i><em>0</em></a>
        </div>
    </div>
    <div class="ec9-nav-wrap">
        <div class="ec9-container ec9-nav">
            <button class="ec9-category-trigger" type="button" data-ec9-categories><i class="fa-solid fa-table-cells-large"></i>{{ $t('EC900.nav.categories') }}<i class="fa-solid fa-chevron-down"></i></button>
            <button class="ec9-mobile-menu" type="button" data-ec9-menu><i class="fa-solid fa-bars"></i><span>Menu</span></button>
            <nav data-ec9-nav>@foreach($nav as $item)<a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</nav>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
