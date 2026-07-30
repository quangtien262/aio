@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC910', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? '')) ?: $t('EC910.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '')) ?: '0399162342';
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) {
        $nav = collect([
            ['label' => $t('EC910.nav.home'), 'url' => route('site.home')],
            ['label' => $t('EC910.nav.about'), 'url' => route('site.home').'#gioi-thieu'],
            ['label' => $t('EC910.nav.brands'), 'url' => route('site.home').'#thuong-hieu'],
            ['label' => $t('EC910.nav.men'), 'url' => route('site.home').'#dong-ho-nam'],
            ['label' => $t('EC910.nav.women'), 'url' => route('site.catalog.search')],
            ['label' => $t('EC910.nav.knowledge'), 'url' => route('site.home').'#kinh-nghiem'],
            ['label' => $t('EC910.nav.news'), 'url' => route('site.blog.index')],
            ['label' => $t('EC910.nav.contact'), 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="ec10-header">
    <div class="ec10-container ec10-head-main">
        <div class="ec10-head-contact">
            <a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>Hotline: {{ $hotline }}</a>
            <a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i>Hệ thống cửa hàng</a>
        </div>
        <a class="ec10-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $name }}">
            @else<span><i class="fa-regular fa-clock"></i><b>DOLA</b><strong>WATCH</strong></span>@endif
        </a>
        <div class="ec10-head-actions">
            <form action="{{ route('site.catalog.search') }}"><input name="q" placeholder="{{ $t('EC910.search.placeholder') }}"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-circle-user"></i></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-circle-user"></i></a>@endguest
            <a href="{{ route('site.catalog.search') }}" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><em>0</em></a>
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-bag-shopping"></i><em>0</em></a>
        </div>
    </div>
    <div class="ec10-nav-wrap">
        <div class="ec10-container">
            <button class="ec10-menu" type="button" data-ec10-menu><i class="fa-solid fa-bars"></i> Menu</button>
            <nav data-ec10-nav>@foreach($nav as $index => $item)<a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</nav>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
