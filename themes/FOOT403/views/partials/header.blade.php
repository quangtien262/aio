@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Dola Restaurant'))) ?: 'Dola Restaurant';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#')])->values();
    if ($navItems->isEmpty()) $navItems = collect([['label'=>'Trang chủ','href'=>'#top'],['label'=>'Giới thiệu','href'=>'#gioi-thieu'],['label'=>'Menu','href'=>'#thuc-don'],['label'=>'Món ăn nổi bật','href'=>'#mon-noi-bat'],['label'=>'Món ngon mỗi ngày','href'=>'#danh-muc'],['label'=>'Tin tức','href'=>'#tin-tuc'],['label'=>'Liên hệ','href'=>'#lien-he']]);
@endphp
<header class="dr-header">
    <div class="dr-container dr-header__inner">
        <a class="dr-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">@if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="dr-brand__seal">♨</span><span><strong>Dola</strong><small>Restaurant</small></span>@endif</a>
        <button class="dr-menu-toggle" type="button" data-dr-menu-toggle aria-expanded="false">☰</button>
        <nav class="dr-nav" data-dr-menu>@foreach($navItems as $item)<a href="{{ $item['href'] }}">{{ $item['label'] }}</a>@endforeach</nav>
        <div class="dr-tools"><a href="{{ route('site.catalog.search', ['locale'=>app()->getLocale(),'searchSegment'=>app()->getLocale()==='en'?'search':'tim-kiem']) }}" aria-label="Tìm kiếm">⌕</a><a href="{{ route('site.cart.index', ['locale'=>app()->getLocale(),'cartSegment'=>app()->getLocale()==='en'?'cart':'gio-hang']) }}" aria-label="Giỏ hàng">♧</a><a class="dr-book" href="#lien-he">Đặt bàn</a></div>
    </div>
</header>
