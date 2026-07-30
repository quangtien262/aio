@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $companyName = data_get($siteProfile ?? [], 'site_name', 'Bøhu.');
    $logoUrl = data_get($branding, 'logo_url') ?: data_get($siteProfile ?? [], 'logo_url');
    $fallbackMenu = [
        ['label' => 'Giới thiệu', 'href' => '#gioi-thieu'],
        ['label' => 'Dịch vụ', 'href' => '#dich-vu'],
        ['label' => 'Tin tức', 'href' => '#tin-tuc'],
        ['label' => 'Thư viện', 'href' => '#thu-vien'],
        ['label' => 'Liên hệ', 'href' => '#lien-he'],
    ];
    $anchorLabels = [
        '#gioi-thieu' => 'Giới thiệu',
        '#dich-vu' => 'Dịch vụ',
        '#tin-tuc' => 'Tin tức',
        '#thu-vien' => 'Thư viện',
        '#lien-he' => 'Liên hệ',
    ];
    $menuItems = collect($landingMenuItems ?? data_get($menus ?? [], 'primary-navigation') ?? data_get($menus ?? [], 'primary') ?? $fallbackMenu)
        ->map(function ($item) use ($anchorLabels) {
            $item = is_array($item) ? $item : [];
            $href = $item['href'] ?? $item['url'] ?? '#';
            if (isset($anchorLabels[$href])) {
                $item['label'] = $anchorLabels[$href];
            }
            return $item;
        })
        ->filter(fn ($item) => filled($item['label'] ?? $item['title'] ?? null))
        ->values();
@endphp
<header class="ser103-header" data-ser103-header>
    <div class="ser103-container ser103-header__inner">
        <a class="ser103-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if(filled($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>
        <button class="ser103-menu-toggle" type="button" data-ser103-menu-toggle aria-label="Mở menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="ser103-nav" data-ser103-menu>
            @foreach($menuItems as $item)
                <a href="{{ $item['href'] ?? $item['url'] ?? '#' }}">{{ $item['label'] ?? $item['title'] }}</a>
            @endforeach
        </nav>
        <div class="ser103-header__actions">
            <a href="{{ route('site.catalog.search') }}" aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></a>
            <button class="ser103-booking-button" type="button" data-ser103-booking-open>
                <span>Đặt lịch hẹn</span><i class="fa-solid fa-arrow-right-long"></i>
            </button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
