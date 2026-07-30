@php
    $th5ProfileBranding = (array) data_get($siteProfile ?? [], 'branding', []);
    $th5Branding = array_replace(
        (array) data_get($themeShellData ?? [], 'branding', []),
        $th5ProfileBranding,
    );
    $th5Company = trim((string) ($th5Branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'An Nhiên Nest'))) ?: 'An Nhiên Nest';
    $th5Logo = trim((string) ($th5ProfileBranding['logo_url'] ?? $th5Branding['logo_url'] ?? ''));
    $th5Hotline = trim((string) ($th5ProfileBranding['support_hotline'] ?? $th5Branding['support_hotline'] ?? ''));
    $th5Phone = preg_replace('/\D+/', '', $th5Hotline) ?: $th5Hotline;
    $th5CartCount = (int) collect(session('storefront_cart.items', []))->sum('quantity');
    $th5Menu = collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->values();
    if ($th5Menu->isEmpty()) {
        $th5Menu = collect([
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ'), 'url' => route('site.home')],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.9e28585ff16ae5cb', 'Bộ sưu tập'), 'url' => '#bo-suu-tap'],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'), 'url' => '#san-pham'],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.1e1ad907f72be113', 'Giới thiệu'), 'url' => '#gioi-thieu'],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.e6475c2dd31c260b', 'Tin tức'), 'url' => '#tin-tuc'],
            ['label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TH0050', app()->getLocale(), 'legacy_inline.416dc399394e8648', 'Liên hệ'), 'url' => '#lien-he'],
        ]);
    }
@endphp
<header class="th5-header" data-th5-header>
    <div class="th5-topbar"><div class="th5-container"><span><i class="fa-solid fa-bell"></i> @themeT('TH0050.header.promotion')</span>@if($th5Hotline !== '')<a href="tel:{{ $th5Phone }}"><i class="fa-regular fa-comments"></i><small>@themeT('TH0050.header.hotline')</small><strong>{{ $th5Hotline }}</strong></a>@endif</div></div>
    <div class="th5-head-main"><div class="th5-container th5-head-main__inner">
        <a class="th5-logo" href="{{ route('site.home') }}" aria-label="{{ $th5Company }}">
            @if($th5Logo !== '')<img src="{{ $th5Logo }}" alt="{{ $th5Company }}">@endif
        </a>
        <form class="th5-search" action="{{ route('site.catalog.search') }}"><input name="q" value="{{ request('q') }}" placeholder="@themeT('TH0050.header.search')"><button aria-label="@themeT('TH0050.header.search')"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="th5-head-actions"><a href="tel:{{ $th5Phone }}"><i class="fa-solid fa-location-dot"></i><span>Cửa hàng</span></a>@auth('customer')<a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>@themeT('TH0050.header.account')</span></a>@else<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>@themeT('TH0050.header.account')</span></button>@endauth<a class="th5-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><b>{{ $th5CartCount }}</b><span>@themeT('TH0050.header.cart')</span></a></div>
        <button class="th5-menu-toggle" type="button" data-th5-menu-toggle aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
    </div></div>
    <div class="th5-nav-wrap"><div class="th5-container"><a class="th5-category-link" href="{{ route('site.catalog.search') }}"><i class="fa-solid fa-border-all"></i> Danh mục sản phẩm</a><nav class="th5-nav" data-th5-menu>@foreach($th5Menu as $item)<a href="{{ $item['url'] ?? $item['href'] ?? '#' }}">{{ $item['label'] ?? $item['title'] }}</a>@endforeach</nav><a class="th5-hot-deal" href="#uu-dai"><i class="fa-solid fa-gift"></i> Hot deal</a></div></div>
</header>
@include('partials.storefront-language-switcher')
