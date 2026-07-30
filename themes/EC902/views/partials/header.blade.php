@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC902', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? '')) ?: $t('EC902.brand.default');
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? null))->values();
    if ($nav->isEmpty()) $nav = collect([
        ['label' => $t('EC902.nav.home'), 'url' => route('site.home')],
        ['label' => $t('EC902.nav.about'), 'url' => route('site.home').'#danh-muc'],
        ['label' => $t('EC902.nav.products'), 'url' => route('site.catalog.search')],
        ['label' => $t('EC902.nav.news'), 'url' => route('site.blog.index')],
        ['label' => $t('EC902.nav.reviews'), 'url' => route('site.home').'#video-review'],
        ['label' => $t('EC902.nav.faq'), 'url' => route('site.contact')],
        ['label' => $t('EC902.nav.contact'), 'url' => route('site.contact')],
    ]);
@endphp
<header class="ec92-header">
    <div class="ec92-top">
        <div class="ec92-container ec92-top-inner">
            <a class="ec92-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif</a>
            <button class="ec92-category-button" type="button" data-ec92-menu><i class="fa-solid fa-bars"></i><span>Danh mục</span></button>
            <form class="ec92-header-search" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Bạn cần tìm gì..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
            <div class="ec92-quick"><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i><span>Hotline<b>{{ $hotline }}</b></span></a><a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i><span>Hệ thống<b>cửa hàng</b></span></a><a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><span>Giỏ hàng<b>Sản phẩm 0</b></span></a>@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-circle-user"></i><span>Thông tin</span></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-circle-user"></i><span>Thông tin</span></a>@endguest</div>
        </div>
    </div>
    <nav class="ec92-nav" data-ec92-nav><div class="ec92-container">@foreach($nav as $item)<a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>@endforeach</div></nav>
</header>
@include('partials.storefront-language-switcher')
