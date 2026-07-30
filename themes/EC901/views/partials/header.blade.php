@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC901', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? '')) ?: $t('EC901.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) {
        $nav = collect([
            ['label' => $t('EC901.nav.home'), 'url' => route('site.home')],
            ['label' => $t('EC901.nav.brands'), 'url' => route('site.home').'#thuong-hieu'],
            ['label' => $t('EC901.nav.news'), 'url' => route('site.blog.index')],
            ['label' => $t('EC901.nav.stores'), 'url' => route('site.contact')],
            ['label' => $t('EC901.nav.contact'), 'url' => route('site.contact')],
            ['label' => $t('EC901.nav.products'), 'url' => route('site.catalog.search')],
        ]);
    }
@endphp
<header class="ec91-header">
    <div class="ec91-container ec91-nav">
        <a class="ec91-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $name }}">
            @else<span><b>{{ $name }}</b><small>{{ $t('EC901.brand.tagline') }}</small></span>@endif
        </a>
        <button class="ec91-menu-button" type="button" data-ec91-menu><i class="fa-solid fa-bars"></i><span>Menu</span></button>
        <nav data-ec91-nav>@foreach($nav as $item)<a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</nav>
        <div class="ec91-actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button>@else<a href="{{ route('customer.account') }}" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></a>@endguest
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-bag-shopping"></i><em>0</em></a>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
