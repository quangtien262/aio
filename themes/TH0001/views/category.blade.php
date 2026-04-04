@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $sidePromos = $shell['side_banners'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@th0001.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $activeFilters = $filters ?? [];
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';

    $productCollection = collect($products ?? []);
    $minPrice = (int) ($activeFilters['available_min_price'] ?? 0);
    $maxPrice = (int) ($activeFilters['available_max_price'] ?? 0);
    $selectedMinPrice = (int) ($activeFilters['selected_min_price'] ?? $minPrice);
    $selectedMaxPrice = (int) ($activeFilters['selected_max_price'] ?? $maxPrice);
    $selectedSort = (string) ($activeFilters['sort'] ?? 'default');

    $queryForUrl = function (array $overrides = []) use ($selectedSort, $selectedMinPrice, $selectedMaxPrice, $minPrice, $maxPrice): array {
        $query = [
            'sort' => $selectedSort,
            'min_price' => $selectedMinPrice,
            'max_price' => $selectedMaxPrice,
        ];

        foreach ($overrides as $key => $value) {
            $query[$key] = $value;
        }

        if (($query['sort'] ?? 'default') === 'default') {
            unset($query['sort']);
        }

        if (($query['min_price'] ?? $minPrice) <= $minPrice) {
            unset($query['min_price']);
        }

        if (($query['max_price'] ?? $maxPrice) >= $maxPrice) {
            unset($query['max_price']);
        }

        return $query;
    };

    $categoryLinks = collect($sidebarCategories ?? [])->map(function (array $child) use ($queryForUrl): array {
        $query = $queryForUrl([]);

        $baseUrl = $child['url'];

        return [
            'label' => $child['label'],
            'url' => $query === [] ? $baseUrl : $baseUrl.'?'.http_build_query($query),
            'count' => (int) ($child['count'] ?? 0),
            'active' => (bool) ($child['active'] ?? false),
        ];
    });

    if ($categoryLinks->isEmpty()) {
        $rootCategoryUrl = '/danh-muc/'.$category->slug;
        $query = $queryForUrl([]);
        $categoryLinks = collect([[
            'label' => $category->name,
            'url' => $query === [] ? $rootCategoryUrl : $rootCategoryUrl.'?'.http_build_query($query),
            'count' => $productCollection->count(),
            'active' => true,
        ]]);
    }

    $sortOptions = [
        ['label' => 'Mặc định', 'value' => 'default'],
        ['label' => 'Bán chạy', 'value' => 'bestseller'],
        ['label' => 'Giá thấp nhất', 'value' => 'price_asc'],
        ['label' => 'Giá cao nhất', 'value' => 'price_desc'],
        ['label' => 'Mới nhất', 'value' => 'newest'],
    ];
    $footerColumns = [
        'Trợ giúp' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership'],
        'Giới thiệu' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động'],
        'Hợp tác' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $category->name }} | {{ data_get($branding, 'company_name', 'TH0001') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-red-deep: #d91c20;
                --th-ink: #222;
                --th-muted: #757575;
                --th-line: #e6e6e6;
                --th-bg: #f6f6f8;
                --th-surface: #fff;
                --th-green: #79c400;
                --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
            }

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            button { font: inherit; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .th-topbar { background: #f3f3f3; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .th-topbar-inner { padding: 6px 0; }
            .th-inline { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
            .th-inline-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .th-inline-form { margin: 0; }
            .th-accent { color: var(--th-red); }
            .th-header { background: var(--th-surface); }
            .th-header-inner { padding: 12px 0; }
            .th-logo { display: flex; align-items: center; gap: 12px; min-width: 220px; }
            .th-logo img { width: 160px; height: 52px; object-fit: contain; }
            .th-logo-mark { display: flex; flex-direction: column; font-size: 12px; line-height: 1.35; color: #555; }
            .th-logo-mark strong { color: var(--th-red); font-size: 16px; }
            .th-search { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 52px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; background: #fff; max-width: 720px; }
            .th-search input, .th-search button { border: 0; height: 44px; font-size: 14px; }
            .th-search input { padding: 0 14px; background: transparent; }
            .th-search button { background: var(--th-red); color: #fff; font-weight: 700; cursor: pointer; }
            .th-cart { min-width: 120px; display: flex; justify-content: flex-end; font-weight: 700; color: #5f5f5f; }
            .th-main-nav { background: var(--th-red); color: #fff; }
            .th-main-nav-inner { position: relative; min-height: 42px; justify-content: flex-start; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; text-align: left; text-transform: uppercase; }
            .th-main-nav-categories-wrap { position: relative; }
            .th-main-nav-categories { background: rgba(0, 0, 0, 0.08); min-width: 210px; padding: 11px 14px; font-weight: 700; }
            .th-category-panel { position: absolute; top: 100%; left: 0; width: 220px; background: #fff; border: 1px solid var(--th-line); z-index: 30; display: none; }
            .th-main-nav-categories-wrap:hover .th-category-panel { display: block; }
            .th-sidebar-entry { position: static; }
            .th-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 14px; border-bottom: 1px solid var(--th-line); font-size: 14px; color: #4f4f4f; background: #fff; transition: background .16s ease, color .16s ease; }
            .th-sidebar-entry:last-child .th-sidebar-item { border-bottom: 0; }
            .th-sidebar-entry:hover .th-sidebar-item { color: var(--th-red); background: #fff7f7; }
            .th-sidebar-item.is-accent { color: var(--th-red); font-weight: 700; }
            .th-sidebar-icon { width: 20px; color: #979797; }
            .th-sidebar-mega { position: absolute; top: -1px; left: 100%; width: calc(100vw - max((100vw - 1200px) / 2, 12px) * 2 - 220px); max-width: 948px; min-height: 302px; display: grid; grid-template-columns: minmax(0, 1fr) 220px; background: #fff; border: 1px solid var(--th-line); box-shadow: 0 24px 48px rgba(21, 24, 34, 0.12); z-index: 8; opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(12px, 0, 0); transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-sidebar-mega::before { content: ''; position: absolute; top: 0; left: -20px; width: 20px; height: 100%; }
            .th-sidebar-entry:hover .th-sidebar-mega { opacity: 1; visibility: visible; pointer-events: auto; transform: translate3d(0, 0, 0); }
            .th-sidebar-mega-content { display: grid; grid-template-columns: 170px 1fr 1.15fr; gap: 34px; padding: 22px 26px 22px 24px; align-content: start; }
            .th-sidebar-mega-content.has-four .th-sidebar-mega-column:nth-child(4) { grid-column: 1 / 2; align-self: start; }
            .th-sidebar-mega.mega-hot { max-width: 920px; grid-template-columns: minmax(0, 1fr) 218px; }
            .th-sidebar-mega.mega-hot .th-sidebar-mega-content { grid-template-columns: 180px 1fr 1fr; gap: 30px; }
            .th-sidebar-mega.mega-food { max-width: 930px; grid-template-columns: minmax(0, 1fr) 220px; }
            .th-sidebar-mega.mega-food .th-sidebar-mega-content { grid-template-columns: 190px 190px 1fr; gap: 28px; }
            .th-sidebar-mega.mega-beauty { max-width: 968px; grid-template-columns: minmax(0, 1fr) 220px; }
            .th-sidebar-mega.mega-beauty .th-sidebar-mega-content { grid-template-columns: 140px 190px 1fr; gap: 28px 34px; }
            .th-sidebar-mega-column h4 { margin: 0 0 14px; font-size: 14px; line-height: 1.35; color: #1f1f1f; text-transform: uppercase; font-weight: 800; }
            .th-sidebar-mega-column ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
            .th-sidebar-mega-column a { color: #5f5f5f; font-size: 13px; line-height: 1.45; }
            .th-sidebar-mega-column a:hover { color: var(--th-red); }
            .th-sidebar-mega-promo { display: grid; gap: 8px; padding: 0; background: #fafafa; border-left: 1px solid var(--th-line); }
            .th-sidebar-mega-promo a { position: relative; min-height: 69px; overflow: hidden; }
            .th-sidebar-mega-promo img { width: 100%; height: 100%; object-fit: cover; }
            .th-sidebar-mega-promo span { position: absolute; left: 12px; bottom: 10px; right: 12px; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.45); }
            .th-page { padding-bottom: 40px; }
            .breadcrumb { display: flex; align-items: center; gap: 8px; padding: 14px 0; color: #8a8a8a; font-size: 13px; }
            .catalog-layout { display: grid; grid-template-columns: 258px minmax(0, 1fr); gap: 18px; }
            .filter-stack { display: grid; gap: 14px; }
            .filter-card { background: #fff; border: 1px solid var(--th-line); }
            .filter-card-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--th-line); font-size: 14px; font-weight: 800; color: #4c4c4c; text-transform: uppercase; }
            .filter-card-header strong { display: flex; align-items: center; gap: 10px; }
            .filter-title-icon { width: 24px; height: 24px; display: grid; place-items: center; background: #fff3f3; color: var(--th-red); border-radius: 6px; font-size: 13px; }
            .filter-card-body { padding: 14px 16px; display: flex; flex-wrap: wrap; gap: 10px; }
            .filter-card-body.is-stacked { display: grid; gap: 12px; }
            .filter-link { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; max-width: 100%; color: #555; padding: 4px 0; background: transparent; }
            .filter-link.active { color: var(--th-red); font-weight: 700; }
            .filter-link-main { display: inline-flex; align-items: center; gap: 9px; min-width: 0; }
            .filter-item-icon { width: 16px; color: #8b8b8b; text-align: center; font-size: 12px; }
            .filter-link small { color: #9a9a9a; }
            .filter-option { display: inline-flex; align-items: center; justify-content: space-between; gap: 10px; width: fit-content; max-width: 100%; color: #555; font-size: 14px; padding: 9px 12px; border: 1px solid #dedede; background: #fff; }
            .filter-check { width: 16px; height: 16px; border: 1px solid #cfcfcf; background: #fff; display: inline-block; }
            .filter-option-meta { display: flex; align-items: center; gap: 8px; }
            .price-range { position: relative; height: 22px; margin: 6px 4px 10px; }
            .price-track { position: absolute; top: 50%; left: 0; right: 0; height: 4px; background: #d5d5d5; border-radius: 999px; transform: translateY(-50%); }
            .price-track-fill { position: absolute; top: 50%; height: 4px; background: var(--th-green); border-radius: 999px; transform: translateY(-50%); }
            .price-range input[type="range"] { position: absolute; left: 0; top: 0; width: 100%; height: 22px; margin: 0; background: transparent; pointer-events: none; -webkit-appearance: none; appearance: none; }
            .price-range input[type="range"]::-webkit-slider-runnable-track { height: 4px; background: transparent; }
            .price-range input[type="range"]::-moz-range-track { height: 4px; background: transparent; }
            .price-range input[type="range"]::-webkit-slider-thumb { width: 16px; height: 16px; border-radius: 50%; border: 0; background: #555; cursor: pointer; pointer-events: auto; -webkit-appearance: none; margin-top: -6px; }
            .price-range input[type="range"]::-moz-range-thumb { width: 16px; height: 16px; border-radius: 50%; border: 0; background: #555; cursor: pointer; pointer-events: auto; }
            .price-labels { display: flex; justify-content: space-between; color: #555; font-size: 13px; }
            .price-inputs { display: grid; grid-template-columns: 1fr 20px 1fr; align-items: center; gap: 8px; }
            .price-pill { min-height: 28px; border: 1px solid #d8d8d8; background: #f7f7f7; display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px; }
            .promo-card { overflow: hidden; }
            .promo-card img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; }
            .promo-copy { padding: 14px 16px; display: grid; gap: 6px; }
            .promo-copy strong { color: var(--th-red); }
            .catalog-main { display: grid; align-content: start; gap: 16px; }
            .catalog-toolbar { display: flex; align-items: center; justify-content: flex-start; gap: 16px; min-height: 52px; height: auto; background: #fff; border: 1px solid var(--th-line); border-top: 4px solid var(--th-green); padding: 0 16px; }
            .catalog-heading { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 10px 0; }
            .catalog-heading-icon { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; background: var(--th-green); color: #fff; font-size: 16px; font-weight: 900; }
            .catalog-heading h1 { margin: 0; font-size: 26px; color: #000000; text-transform: uppercase; }
            .catalog-heading p { margin: 4px 0 0; color: #8a8a8a; font-size: 13px; line-height: 1.4; }
            .sort-list { display: grid; gap: 10px; }
            .sort-pill { width: 100%; display: flex; align-items: center; gap: 10px; border: 0; background: transparent; color: #666; min-height: 28px; padding: 0; cursor: pointer; text-align: left; }
            .sort-pill.is-active { color: var(--th-green-dark, #4e9620); font-weight: 700; }
            .sort-pill-icon { width: 16px; color: #8b8b8b; text-align: center; font-size: 12px; }
            .sort-pill.is-active .sort-pill-icon { color: var(--th-green-dark, #4e9620); }
            .quick-chip-row { display: flex; gap: 10px; flex-wrap: wrap; }
            .quick-chip { border: 1px solid #d8d8d8; background: #fff; color: #666; padding: 8px 12px; font-size: 13px; }
            .product-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .product-card { background: #fff; border: 1px solid var(--th-line); overflow: hidden; transition: transform .18s ease, box-shadow .18s ease; }
            .product-card:hover { transform: translateY(-3px); box-shadow: var(--th-shadow); }
            .product-media { position: relative; aspect-ratio: 1 / 1; background: #f1f1f1; overflow: hidden; }
            .product-media img { width: 100%; height: 100%; object-fit: cover; }
            .product-badge { position: absolute; right: 10px; bottom: 10px; background: rgba(22,22,22,0.68); color: #fff; padding: 4px 8px; border-radius: 999px; font-size: 11px; }
            .product-body { padding: 12px 12px 14px; }
            .product-title { margin: 0 0 12px; min-height: 44px; font-size: 15px; line-height: 1.45; color: #2f2f2f; }
            .product-pricing { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
            .product-price { color: var(--th-red); font-size: 20px; font-weight: 900; letter-spacing: -0.04em; }
            .product-discount { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 6px; background: var(--th-red); color: #fff; font-size: 13px; font-weight: 800; }
            .product-old-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 6px; color: #a8a8a8; font-size: 13px; }
            .product-old-price { text-decoration: line-through; }
            .product-stock { color: #9d9d9d; }
            .empty-state { background: #fff; border: 1px solid var(--th-line); padding: 28px; color: #666; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }

            @media (max-width: 1100px) {
                .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .catalog-layout { grid-template-columns: 1fr; }
                .th-category-panel .th-sidebar-mega { display: none !important; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner, .catalog-toolbar { flex-direction: column; align-items: stretch; }
                .th-search { max-width: none; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-main-nav-categories { min-width: 0; width: 100%; }
                .th-category-panel { width: min(320px, calc(100vw - 24px)); }
                .product-grid, .th-footer-grid { grid-template-columns: 1fr; }
                .catalog-heading h1 { font-size: 26px; }
                .catalog-toolbar { padding: 12px 16px; }
                .catalog-heading { padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="th-page">
            <div class="th-topbar">
                <div class="th-container th-topbar-inner">
                    <div class="th-inline">
                        <span>📍 {{ $contactLocation }}</span>
                        <button type="button" class="th-inline-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? '📩 Đã đăng ký bản tin' : '📩 Đăng ký bản tin' }}</button>
                    </div>
                    <div class="th-inline">
                        <span>📞 Hotline: <span class="th-accent">{{ $contactHotline }}</span></span>
                        <span>✉ Email: {{ $contactEmail }}</span>
                        @if (!empty($customerAuth['is_authenticated']))
                            <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>
                            <form class="th-inline-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                                @csrf
                                <button type="submit" class="th-inline-action">Đăng xuất</button>
                            </form>
                        @else
                            <button type="button" class="th-inline-action" data-open-auth-modal="register">Đăng ký</button>
                            <button type="button" class="th-inline-action" data-open-auth-modal="login">Đăng nhập</button>
                        @endif
                    </div>
                </div>
            </div>

            <header class="th-header">
                <div class="th-container th-header-inner">
                    <a class="th-logo" href="/">
                        <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'Website logo') }}">
                        <span class="th-logo-mark">
                            <strong>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'AIO Commerce')) }}</strong>
                            <span>Danh sách sản phẩm</span>
                        </span>
                    </a>
                    <div class="th-search">
                        <input type="text" value="{{ $category->name }}" aria-label="Tìm kiếm sản phẩm" readonly>
                        <button type="button">Tìm</button>
                    </div>
                    <a class="th-cart" href="{{ route('site.cart.index') }}">🛒 {{ $cartSummary['count'] ?? 0 }} GIỎ HÀNG</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories-wrap">
                        <div class="th-main-nav-categories">DANH MỤC</div>
                        <div class="th-category-panel">
                            @foreach ($productMenu as $item)
                                <div class="th-sidebar-entry">
                                    <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}" class="th-sidebar-item {{ !empty($item['highlight']) ? 'is-accent' : '' }}">
                                        <span><span class="th-sidebar-icon">{{ $item['icon'] ?? '◌' }}</span> {{ $item['label'] ?? 'Danh mục' }}</span>
                                        <span>›</span>
                                    </a>

                                    @if (!empty($item['children']))
                                        @php
                                            $submenuColumns = collect($item['children'])->chunk(3);
                                        @endphp
                                        <div class="th-sidebar-mega {{ $loop->first ? 'mega-hot' : ($loop->index % 2 === 0 ? 'mega-beauty' : 'mega-food') }}">
                                            <div class="th-sidebar-mega-content {{ $submenuColumns->count() > 3 ? 'has-four' : '' }}">
                                                @foreach ($submenuColumns as $chunk)
                                                    <div class="th-sidebar-mega-column">
                                                        <h4>{{ $item['label'] ?? 'Danh mục' }}</h4>
                                                        <ul>
                                                            @foreach ($chunk as $child)
                                                                <li><a href="{{ $child['url'] ?? ($item['url'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? 'Nhóm con' }}</a></li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="th-sidebar-mega-promo">
                                                @foreach ($sidePromos as $promo)
                                                    <a href="{{ $promo['link_url'] ?? '#featured' }}">
                                                        <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                                        <span>{{ $promo['title'] }}{{ filled($promo['subtitle'] ?? null) ? ' · '.$promo['subtitle'] : '' }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="th-main-nav-menu">
                        @foreach ($topMenu as $item)
                            <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                        @endforeach
                    </div>
                </div>
            </nav>

            <main class="th-container">
                <div class="breadcrumb">
                    <a href="/">Trang chủ</a>
                    <span>›</span>
                    @if ($category->parent)
                        <a href="/danh-muc/{{ $category->parent->slug }}">{{ $category->parent->name }}</a>
                        <span>›</span>
                    @endif
                    <span>{{ $category->name }}</span>
                </div>

                <section class="catalog-layout">
                    <aside class="filter-stack">
                        <section class="filter-card">
                            <div class="filter-card-header">
                                <strong><span class="filter-title-icon">☰</span> Danh mục</strong>
                            </div>
                            <div class="filter-card-body">
                                @foreach ($categoryLinks as $item)
                                    <a href="{{ $item['url'] }}" class="filter-link {{ !empty($item['active']) ? 'active' : '' }}">
                                        <span class="filter-link-main"><span class="filter-item-icon">▸</span><span>{{ $item['label'] }}</span></span>
                                        <small>({{ $item['count'] }})</small>
                                    </a>
                                @endforeach
                            </div>
                        </section>

                        <section class="filter-card">
                            <div class="filter-card-header">
                                <strong><span class="filter-title-icon">⇅</span> Sắp xếp theo</strong>
                                <span>⌃</span>
                            </div>
                            <div class="filter-card-body is-stacked">
                                <div class="sort-list">
                                    @foreach ($sortOptions as $option)
                                        @php
                                            $sortQuery = $queryForUrl(['sort' => $option['value']]);
                                        @endphp
                                        <a href="{{ request()->url() }}{{ $sortQuery === [] ? '' : '?'.http_build_query($sortQuery) }}" class="sort-pill {{ $selectedSort === $option['value'] ? 'is-active' : '' }}"><span class="sort-pill-icon">▸</span><span>{{ $option['label'] }}</span></a>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section class="filter-card">
                            <div class="filter-card-header">
                                <strong><span class="filter-title-icon">$</span> Khoảng giá</strong>
                                <span>⌃</span>
                            </div>
                            <form method="GET" action="{{ request()->url() }}" class="filter-card-body is-stacked">
                                @if ($selectedSort !== 'default')
                                    <input type="hidden" name="sort" value="{{ $selectedSort }}">
                                @endif
                                <div class="price-range" data-price-range data-min="{{ $minPrice }}" data-max="{{ $maxPrice }}">
                                    <div class="price-track"></div>
                                    <div class="price-track-fill" data-price-range-fill></div>
                                    <input type="range" min="{{ $minPrice }}" max="{{ $maxPrice }}" value="{{ $selectedMinPrice }}" step="1000" data-price-range-min name="min_price" aria-label="Giá thấp nhất">
                                    <input type="range" min="{{ $minPrice }}" max="{{ $maxPrice }}" value="{{ $selectedMaxPrice }}" step="1000" data-price-range-max name="max_price" aria-label="Giá cao nhất">
                                </div>
                                <div class="price-labels">
                                    <span data-price-label-min>{{ number_format($selectedMinPrice, 0, ',', '.') }}</span>
                                    <span data-price-label-max>{{ number_format($selectedMaxPrice, 0, ',', '.') }}</span>
                                </div>
                                <div class="price-inputs">
                                    <div class="price-pill" data-price-value-min>{{ number_format($selectedMinPrice, 0, ',', '.') }}</div>
                                    <span style="text-align:center;color:#999;">-</span>
                                    <div class="price-pill" data-price-value-max>{{ number_format($selectedMaxPrice, 0, ',', '.') }}</div>
                                </div>
                            </form>
                        </section>

                        @if (($sidePromos[0] ?? null) !== null)
                            <section class="filter-card promo-card">
                                <img src="{{ $sidePromos[0]['image'] }}" alt="{{ $sidePromos[0]['title'] }}">
                                <div class="promo-copy">
                                    <strong>{{ $sidePromos[0]['title'] }}</strong>
                                    <span>{{ $sidePromos[0]['subtitle'] ?? 'Ưu đãi đang chạy trong theme TH0001.' }}</span>
                                </div>
                            </section>
                        @endif
                    </aside>

                    <div class="catalog-main">
                        <section class="catalog-toolbar">
                            <div class="catalog-heading">
                                <div class="catalog-heading-icon">⌘</div>
                                <div>
                                    <h1>{{ $category->name }}</h1>
                                    <p>{{ $productCollection->count() }} sản phẩm đang hiển thị</p>
                                </div>
                            </div>
                        </section>

                        @if (($childCategories ?? []) !== [])
                            <div class="quick-chip-row">
                                @foreach ($childCategories as $child)
                                    <a href="{{ $child['url'] }}" class="quick-chip">{{ $child['name'] }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if ($productCollection->isNotEmpty())
                            <section class="product-grid">
                                @foreach ($products as $product)
                                    <article class="product-card">
                                        <div class="product-media">
                                            <a href="{{ $product['url'] }}">
                                                <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}">
                                            </a>
                                            <span class="product-badge">{{ $product['tag'] ?? 'Sản phẩm' }}</span>
                                        </div>

                                        <div class="product-body">
                                            <h3 class="product-title"><a href="{{ $product['url'] }}">{{ $product['title'] }}</a></h3>
                                            <div class="product-pricing">
                                                <span class="product-price">{{ $formatCurrency($product['price'] ?? null) }}</span>
                                                @if (($product['discount'] ?? 0) > 0)
                                                    <span class="product-discount">-{{ (int) $product['discount'] }}%</span>
                                                @endif
                                            </div>
                                            <div class="product-old-row">
                                                <span class="product-old-price">{{ $formatCurrency($product['old_price'] ?? null) }}</span>
                                                <span class="product-stock">Tồn kho {{ $product['meta'] ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </section>
                        @else
                            <section class="empty-state">
                                Chưa có sản phẩm nào trong danh mục này.
                            </section>
                        @endif
                    </div>
                </section>
            </main>

            <footer class="th-footer">
                <div class="th-container th-footer-inner">
                    <div class="th-footer-grid">
                        @foreach ($footerColumns as $title => $items)
                            <div class="th-footer-card">
                                <h4>{{ $title }}</h4>
                                <div class="th-footer-links">
                                    @foreach ($items as $item)
                                        <span>{{ $item }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <div class="th-company">
                            <strong>{{ data_get($branding, 'company_name', 'TH0001') }}</strong>
                            <div>Thiết kế lại trang danh sách sản phẩm theo layout thương mại điện tử, dùng dữ liệu category và product thật từ hệ thống.</div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <script>
            document.querySelectorAll('[data-price-range]').forEach((rangeRoot) => {
                const minInput = rangeRoot.querySelector('[data-price-range-min]');
                const maxInput = rangeRoot.querySelector('[data-price-range-max]');
                const fill = rangeRoot.querySelector('[data-price-range-fill]');
                const form = rangeRoot.closest('form');
                const labelMin = rangeRoot.parentElement?.querySelector('[data-price-label-min]');
                const labelMax = rangeRoot.parentElement?.querySelector('[data-price-label-max]');
                const valueMin = rangeRoot.parentElement?.querySelector('[data-price-value-min]');
                const valueMax = rangeRoot.parentElement?.querySelector('[data-price-value-max]');
                const minBound = Number(rangeRoot.dataset.min || 0);
                const maxBound = Number(rangeRoot.dataset.max || 0);
                const formatter = new Intl.NumberFormat('vi-VN');
                let submitTimer = null;

                if (!minInput || !maxInput || !fill || maxBound <= minBound) {
                    return;
                }

                const sync = (source) => {
                    let minValue = Number(minInput.value);
                    let maxValue = Number(maxInput.value);

                    if (maxValue - minValue < 1000) {
                        if (source === minInput) {
                            minValue = maxValue - 1000;
                            minInput.value = String(minValue);
                        } else {
                            maxValue = minValue + 1000;
                            maxInput.value = String(maxValue);
                        }
                    }

                    minValue = Math.max(minBound, minValue);
                    maxValue = Math.min(maxBound, maxValue);

                    const startPercent = ((minValue - minBound) / (maxBound - minBound)) * 100;
                    const endPercent = ((maxValue - minBound) / (maxBound - minBound)) * 100;

                    fill.style.left = `${startPercent}%`;
                    fill.style.width = `${endPercent - startPercent}%`;

                    if (labelMin) labelMin.textContent = formatter.format(minValue);
                    if (labelMax) labelMax.textContent = formatter.format(maxValue);
                    if (valueMin) valueMin.textContent = formatter.format(minValue);
                    if (valueMax) valueMax.textContent = formatter.format(maxValue);
                };

                const queueSubmit = () => {
                    if (!form) {
                        return;
                    }

                    window.clearTimeout(submitTimer);
                    submitTimer = window.setTimeout(() => {
                        form.requestSubmit();
                    }, 250);
                };

                minInput.addEventListener('input', () => {
                    sync(minInput);
                    queueSubmit();
                });
                maxInput.addEventListener('input', () => {
                    sync(maxInput);
                    queueSubmit();
                });
                sync();
            });
        </script>
        @include('theme-th0001::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
