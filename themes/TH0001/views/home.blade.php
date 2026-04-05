@php
    $homeData = $themeHomeData ?? [];
    $branding = $homeData['branding'] ?? [];
    $heroBanner = $homeData['hero_banner'] ?? [];
    $sidePromos = $homeData['side_banners'] ?? [];
    $brands = $homeData['brand_highlights'] ?? [];
    $sidebarCategories = $homeData['product_menu'] ?? [];
    $featuredDeals = $homeData['featured_products'] ?? [];
    $featuredTitle = $homeData['featured_title'] ?? 'Sản phẩm nổi bật';
    $sections = $homeData['sections'] ?? [];
    $cartSummary = $homeData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $homeData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $homeData['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@th0001.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $searchCategories = collect($sidebarCategories)->pluck('label')->take(6)->all();
    $heroSlides = collect([$heroBanner])
        ->merge(
            collect($sidePromos)->take(3)->map(function (array $promo, int $index): array {
                return [
                    'image' => $promo['image'] ?? 'https://picsum.photos/seed/th0001-fallback-hero-'.($index + 1).'/960/520',
                    'title' => $promo['title'] ?? 'Ưu đãi nổi bật',
                    'summary' => $promo['subtitle'] ?? 'Khám phá thêm các ưu đãi đang chạy trong storefront TH0001.',
                    'eyebrow' => 'Ưu đãi nổi bật',
                    'badge' => 'Khám phá ngay',
                    'cta' => 'Xem ngay',
                    'link_url' => $promo['link_url'] ?? '#featured',
                ];
            })
        )
        ->filter(fn ($slide): bool => is_array($slide) && filled($slide['image'] ?? null))
        ->values();

    $footerColumns = [
        'Trợ giúp' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership'],
        'Giới thiệu' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động'],
        'Hợp tác' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí'],
    ];

    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $formatDiscount = fn ($value) => '-'.(int) $value.'%';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'TH0001 Deal Commerce')) }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-red-deep: #d91c20;
                --th-ink: #222222;
                --th-muted: #6d6d6d;
                --th-line: #e6e6e6;
                --th-bg: #f6f6f8;
                --th-surface: #ffffff;
                --th-green: #79c400;
                --th-pink: #ff4f92;
                --th-lime: #86c440;
                --th-orange: #ff8c1a;
                --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
            }

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .th-page { min-height: 100vh; }
            .th-topbar { background: #f3f3f3; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
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
            .th-main-nav-inner { min-height: 42px; justify-content: flex-start; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; text-align: left; text-transform: uppercase; }
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 170px; padding: 11px 14px; font-weight: 700; }
            .th-content { padding: 0 0 40px; }
            .th-hero-layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; margin-top: 0; }
            .th-sidebar { position: relative; background: var(--th-surface); border: 1px solid var(--th-line); z-index: 5; }
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
            .th-hero-stack { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 12px; }
            .th-hero-card { background: linear-gradient(90deg, #fff3ea 0%, #fff 100%); min-height: 300px; position: relative; overflow: hidden; border: 1px solid #ffd7bd; }
            .th-hero-slide { position: absolute; inset: 0; opacity: 0; pointer-events: none; transition: opacity .6s ease; }
            .th-hero-slide.is-active { opacity: 1; pointer-events: auto; z-index: 1; }
            .th-hero-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .th-hero-overlay {position: relative;z-index: 1;width: min(54%, 420px);padding: 36px 32px;background: linear-gradient(90deg, rgb(0 0 0 / 95%) 0%, rgb(255 255 255 / 14%) 100%);height: 100%;}
            .th-eyebrow { display: inline-flex; padding: 6px 12px; border-radius: 999px; background: rgba(239,43,45,0.1); color: #ff6668; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
            .th-hero-title { margin: 14px 0 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.05; color: #ffffff; }
            .th-hero-summary { margin: 0 0 20px; color: #ffffff; line-height: 1.6; }
            .th-hero-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .th-badge-price { background: #fff; color: var(--th-red); border-radius: 20px; padding: 10px 14px; font-size: 15px; font-weight: 800; box-shadow: 0 10px 24px rgba(239,43,45,0.14); }
            .th-hero-button { background: linear-gradient(180deg, #ff8e18 0%, #f25c05 100%); color: #fff; border-radius: 999px; padding: 11px 22px; font-weight: 800; text-transform: uppercase; }
            .th-hero-nav { position: absolute; top: 50%; z-index: 3; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; margin-top: -22px; padding: 0; border: 0; border-radius: 999px; background: rgba(255,255,255,.88); color: #303030; font-size: 28px; line-height: 1; text-align: center; box-shadow: 0 12px 24px rgba(19, 21, 33, 0.16); cursor: pointer; opacity: 0; visibility: hidden; transform: translateY(-50%) scale(.92); transition: opacity .2s ease, visibility .2s ease, transform .2s ease, background .18s ease; }
            .th-hero-card:hover .th-hero-nav, .th-hero-card:focus-within .th-hero-nav { opacity: 1; visibility: visible; transform: translateY(-50%) scale(1); }
            .th-hero-nav:hover { background: #fff; transform: translateY(-50%) scale(1.06); }
            .th-hero-nav-prev { left: 5px; }
            .th-hero-nav-next { right: 5px; }
            .th-hero-dots { position: absolute; left: 32px; bottom: 20px; z-index: 3; display: flex; align-items: center; gap: 8px; }
            .th-hero-dot { width: 10px; height: 10px; border: 0; border-radius: 999px; background: rgba(255,255,255,.55); cursor: pointer; transition: transform .18s ease, background .18s ease; }
            .th-hero-dot.is-active { background: #fff; transform: scale(1.25); }
            .th-side-promo-grid { display: grid; gap: 8px; }
            .th-side-promo { min-height: 69px; position: relative; overflow: hidden; border: 1px solid var(--th-line); }
            .th-side-promo img { width: 100%; height: 100%; object-fit: cover; }
            .th-side-promo span { position: absolute; left: 12px; bottom: 10px; z-index: 1; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.45); }
            .th-brand-strip { margin-top: 12px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 14px 16px; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
            .th-brand { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
            .th-brand-badge { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 900; font-size: 11px; box-shadow: var(--th-shadow); }
            .th-section-tabs { display: flex; gap: 24px; font-size: 14px; color: #7d7d7d; text-transform: uppercase; }
            .th-section-tabs span:first-child { color: var(--th-ink); font-weight: 800; }
            .th-featured-panel { margin-top: 22px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 0 0 22px; }
            .th-featured-topbar { padding: 0 16px; display: flex; align-items: center; gap: 24px; min-height: 48px; border-bottom: 1px solid var(--th-line); }
            .th-card-grid { padding: 18px 16px 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .th-deal-card { background: #fff; border: 1px solid var(--th-line); transition: transform .18s ease, box-shadow .18s ease; }
            .th-deal-card:hover { transform: translateY(-3px); box-shadow: var(--th-shadow); }
            .th-deal-image-wrap { position: relative; aspect-ratio: 1 / 1; overflow: hidden; background: #f1f1f1; }
            .th-deal-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
            .th-deal-chip, .th-deal-countdown { position: absolute; bottom: 10px; right: 10px; background: rgba(22,22,22,0.68); color: #fff; padding: 4px 8px; border-radius: 999px; font-size: 11px; }
            .th-deal-countdown { top: 10px; left: 10px; right: auto; bottom: auto; }
            .th-deal-body { padding: 12px 12px 14px; }
            .th-deal-title { margin: 0 0 12px; font-size: 15px; line-height: 1.45; min-height: 44px; }
            .th-pricing { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
            .th-price { color: var(--th-red); font-size: 20px; font-weight: 900; letter-spacing: -0.04em; }
            .th-price small { font-size: 18px; }
            .th-discount { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 6px; background: var(--th-red); color: #fff; font-size: 13px; font-weight: 800; }
            .th-old-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; color: #a8a8a8; font-size: 13px; }
            .th-old-price { text-decoration: line-through; }
            .th-stat { color: #9d9d9d; }
            .th-category-section { margin-top: 26px; background: var(--th-surface); border: 1px solid var(--th-line); }
            .th-category-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 16px; min-height: 52px; border-top: 4px solid var(--th-lime); }
            .th-category-header.pink { border-top-color: var(--th-pink); }
            .th-category-title { display: flex; align-items: center; gap: 12px; min-width: 220px; color: var(--th-lime); font-size: 28px; font-weight: 900; text-transform: uppercase; }
            .th-category-header.pink .th-category-title { color: var(--th-pink); }
            .th-category-title-badge { width: 32px; height: 32px; border-radius: 8px; background: currentColor; color: #fff; display: grid; place-items: center; font-size: 16px; }
            .th-category-filters, .th-category-tabs { display: flex; align-items: center; gap: 22px; font-size: 13px; color: #6f6f6f; flex-wrap: wrap; }
            .th-category-tabs span:first-child, .th-category-filters a:first-child { color: var(--th-ink); font-weight: 800; }
            .th-category-grid { padding: 16px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .th-category-footer { padding: 0 16px 18px; display: flex; justify-content: center; }
            .th-more-button { border: 1px solid var(--th-line); background: #fafafa; color: #6f6f6f; padding: 10px 18px; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }

            @media (max-width: 1100px) {
                .th-hero-layout { grid-template-columns: 1fr; }
                .th-sidebar { display: none; }
                .th-hero-stack { grid-template-columns: 1fr; }
                .th-card-grid, .th-category-grid, .th-brand-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .th-search { grid-template-columns: minmax(0, 1fr) 52px; }
                .th-sidebar-mega { display: none !important; opacity: 0 !important; visibility: hidden !important; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { text-align: center; font-size: 36px; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-categories { min-width: 0; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-hero-overlay { width: 100%; padding: 24px 18px; }
                .th-card-grid, .th-category-grid, .th-brand-strip, .th-footer-grid { grid-template-columns: 1fr; }
                .th-category-header { align-items: flex-start; padding: 12px 16px; }
                .th-category-title { min-width: 0; font-size: 22px; }
                .th-category-tabs, .th-category-filters, .th-inline { gap: 12px; }
                .th-price { font-size: 20px; }
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
                        </span>
                    </a>
                    <form class="th-search" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm sản phẩm / khuyến mãi" aria-label="Tìm kiếm sản phẩm" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                        <button type="submit">Tìm</button>
                    </form>
                    <a class="th-cart" href="{{ route('site.cart.index') }}">🛒 {{ $cartSummary['count'] ?? 0 }} GIỎ HÀNG</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories">DANH MỤC</div>
                    <div class="th-main-nav-menu">
                        @foreach (($homeData['top_menu'] ?? []) as $menuItem)
                            <a href="{{ $menuItem['url'] ?? '#' }}" target="{{ $menuItem['target'] ?? '_self' }}">{{ $menuItem['label'] ?? 'Menu' }}</a>
                        @endforeach
                    </div>
                </div>
            </nav>

            <main class="th-content">
                <div class="th-container">
                    <section class="th-hero-layout">
                        <aside class="th-sidebar">
                            @foreach ($sidebarCategories as $category)
                                <div class="th-sidebar-entry">
                                    <a href="{{ $category['url'] ?? '#' }}" target="{{ $category['target'] ?? '_self' }}" class="th-sidebar-item {{ !empty($category['highlight']) ? 'is-accent' : '' }}">
                                        <span><span class="th-sidebar-icon">{{ $category['icon'] ?? '◌' }}</span> {{ $category['label'] }}</span>
                                        <span>›</span>
                                    </a>

                                    @if (!empty($category['children']))
                                        @php
                                            $submenuColumns = collect($category['children'])->chunk(3);
                                        @endphp
                                        <div class="th-sidebar-mega {{ $loop->first ? 'mega-hot' : ($loop->index % 2 === 0 ? 'mega-beauty' : 'mega-food') }}">
                                            <div class="th-sidebar-mega-content {{ $submenuColumns->count() > 3 ? 'has-four' : '' }}">
                                                @foreach ($submenuColumns as $chunk)
                                                    <div class="th-sidebar-mega-column">
                                                        <h4>{{ $category['label'] }}</h4>
                                                        <ul>
                                                            @foreach ($chunk as $child)
                                                                <li><a href="{{ $child['url'] ?? ($category['url'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? 'Nhóm con' }}</a></li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="th-sidebar-mega-promo">
                                                @foreach ($sidePromos as $promo)
                                                    <a href="{{ $promo['link_url'] ?? '#featured' }}">
                                                        <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                                        <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </aside>

                        <div>
                            <div class="th-hero-stack">
                                @include('theme-th0001::partials.home-hero-slider', ['heroSlides' => $heroSlides])

                                <div class="th-side-promo-grid">
                                    @foreach ($sidePromos as $promo)
                                        <a href="{{ $promo['link_url'] ?? '#featured' }}" class="th-side-promo">
                                            <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                            <span>{{ $promo['title'] }} · {{ $promo['subtitle'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <section class="th-brand-strip">
                                @foreach ($brands as $brand)
                                    <div class="th-brand">
                                        <div class="th-brand-badge" style="background: {{ $brand['tone'] }}">{{ $brand['name'] }}</div>
                                        <strong>{{ $brand['name'] }}</strong>
                                    </div>
                                @endforeach
                            </section>
                        </div>
                    </section>

                    <section id="featured" class="th-featured-panel">
                        <div class="th-featured-topbar">
                            <div class="th-section-tabs">
                                <span>{{ $featuredTitle }}</span>
                                <span>Mới cập nhật</span>
                                <span>Giá tốt</span>
                            </div>
                        </div>

                        <div class="th-card-grid">
                            @foreach ($featuredDeals as $deal)
                                <article class="th-deal-card">
                                    <div class="th-deal-image-wrap">
                                        <a href="{{ $deal['url'] ?? '#' }}">
                                            <img src="{{ $deal['image'] }}" alt="{{ $deal['title'] }}">
                                        </a>
                                        <span class="th-deal-chip">{{ $deal['tag'] ?? 'Sản phẩm' }}</span>
                                    </div>
                                    <div class="th-deal-body">
                                        <h3 class="th-deal-title"><a href="{{ $deal['url'] ?? '#' }}">{{ $deal['title'] }}</a></h3>
                                        <div class="th-pricing">
                                            <span class="th-price">{{ $formatCurrency($deal['price'] ?? null) }}</span>
                                            <span class="th-discount">{{ $formatDiscount($deal['discount'] ?? 0) }}</span>
                                        </div>
                                        <div class="th-old-price-row">
                                            <span class="th-old-price">{{ $formatCurrency($deal['old_price'] ?? null) }}</span>
                                            <span class="th-stat">Tồn kho {{ $deal['meta'] ?? 0 }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    @foreach ($sections as $section)
                        <section id="section-{{ $section['slug'] }}" class="th-category-section">
                            <div class="th-category-header {{ $section['theme'] === 'pink' ? 'pink' : '' }}">
                                <div class="th-category-title">
                                    <span class="th-category-title-badge">{{ $section['theme'] === 'pink' ? '✿' : '🍴' }}</span>
                                    <span>{{ $section['title'] }}</span>
                                </div>

                                <div class="th-category-tabs">
                                    @foreach ($section['tabs'] as $tab)
                                        <span>{{ $tab }}</span>
                                    @endforeach
                                </div>

                                <div class="th-category-filters">
                                    @foreach ($section['filters'] as $filter)
                                        <a href="#">{{ $filter }}</a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="th-category-grid">
                                @foreach ($section['items'] as $item)
                                    <article class="th-deal-card">
                                        <div class="th-deal-image-wrap">
                                            <a href="{{ $item['url'] ?? '#' }}">
                                                <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}">
                                            </a>
                                            <span class="th-deal-countdown">⏱ Còn 21 ngày</span>
                                            <span class="th-deal-chip">{{ $item['tag'] }}</span>
                                        </div>
                                        <div class="th-deal-body">
                                            <h3 class="th-deal-title"><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] }}</a></h3>
                                            <div class="th-pricing">
                                                <span class="th-price">{{ $formatCurrency($item['price'] ?? null) }}</span>
                                                <span class="th-discount">{{ $formatDiscount($item['discount'] ?? 0) }}</span>
                                            </div>
                                            <div class="th-old-price-row">
                                                <span class="th-old-price">{{ $formatCurrency($item['old_price'] ?? null) }}</span>
                                                <span class="th-stat">Tồn kho {{ $item['meta'] ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="th-category-footer">
                                <a href="/danh-muc/{{ $section['slug'] }}" class="th-more-button">Xem tất cả {{ $section['title'] }} mới nhất</a>
                            </div>
                        </section>
                    @endforeach
                </div>
            </main>

            <footer class="th-footer">
                <div class="th-container th-footer-inner">
                    <div class="th-footer-grid">
                        @foreach ($footerColumns as $title => $links)
                            <section class="th-footer-card">
                                <h4>{{ $title }}</h4>
                                <div class="th-footer-links">
                                    @foreach ($links as $link)
                                        <a href="#">{{ $link }}</a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <section class="th-company">
                            <strong>{{ mb_strtoupper(data_get($branding, 'company_name', 'TH0001 DEMO'), 'UTF-8') }}</strong>
                            <div class="th-footer-links">
                                <span>332 Lũy Bán Bích, Phường Hòa Thạnh, Quận Tân Phú, TP.HCM</span>
                                <span>Chi nhánh Hà Nội: Tầng 3, CT2 Ban Cơ Yếu Chính Phủ, Thanh Xuân</span>
                                <span>Hotline: {{ $contactHotline }}</span>
                                <span>Email: {{ $contactEmail }}</span>
                            </div>
                        </section>
                    </div>
                </div>
            </footer>
        </div>
        @include('theme-th0001::partials.product-search-autocomplete')
        @include('theme-th0001::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
