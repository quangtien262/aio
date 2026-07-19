@php
    $homeData = $themeHomeData ?? [];
    $branding = $homeData['branding'] ?? [];
    $serviceCategories = $homeData['service_categories'] ?? [];
    $featuredServices = $homeData['featured_services'] ?? [];
    $serviceRoutes = $homeData['service_routes'] ?? [];
    $serviceMetrics = $homeData['service_metrics'] ?? [];
    $quotePanel = $homeData['quote_panel'] ?? ['badge' => 'Báo giá trong ngày', 'items' => []];
    $serviceHighlights = $homeData['service_highlights'] ?? [];
    $latestPosts = $homeData['latest_posts'] ?? [];
    $latestPostsSection = $homeData['latest_posts_section'] ?? [];
    $topMenu = $homeData['top_menu'] ?? [];
    $productMenu = $homeData['product_menu'] ?? [];
    $cartSummary = $homeData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $homeData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $homeData['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $homeData['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $themeBlockRegistry = app(\App\Support\ThemeBlockRegistry::class);
    $themeKey = 'SER0101';
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $adminUser = auth('admin')->user();
    $canQuickEditThemeBlocks = $adminUser !== null
        && $adminUser->hasPermission('theme.view')
        && $adminUser->hasPermission('theme.customize');
    $quickEditLocales = \App\Support\FrontendLocalization::supportedLocales();
    $quickEditLocaleOptions = \App\Support\FrontendLocalization::localeOptions();
    $quotePanelBadgeEditFields = [[
        'key' => $themeBlockRegistry->contentKey($themeKey, 'quote_panel.badge'),
        'label' => 'Badge',
        'group' => 'content',
        'entity' => 'theme',
    ]];
    $latestPostsSectionEditFields = [
        [
            'key' => $themeBlockRegistry->contentKey($themeKey, 'latest_posts.kicker'),
            'label' => 'Nhãn nhỏ',
            'group' => 'content',
            'entity' => 'theme',
        ],
        [
            'key' => $themeBlockRegistry->contentKey($themeKey, 'latest_posts.title'),
            'label' => 'Tiêu đề',
            'group' => 'content',
            'entity' => 'theme',
        ],
        [
            'key' => $themeBlockRegistry->contentKey($themeKey, 'latest_posts.summary'),
            'label' => 'Mô tả',
            'group' => 'content',
            'entity' => 'theme',
        ],
    ];
    $footerEditFields = [
        ['key' => 'home.footer_summary', 'label' => 'Mô tả footer', 'group' => 'static'],
        ['key' => 'home.footer_contact_title', 'label' => 'Tiêu đề cột liên hệ', 'group' => 'static'],
        ['key' => 'home.footer_nav_title', 'label' => 'Tiêu đề điều hướng', 'group' => 'static'],
        ['key' => 'common.home', 'label' => 'Nhãn Trang chủ', 'group' => 'static'],
        ['key' => 'menu.default.blog', 'label' => 'Nhãn Cẩm nang', 'group' => 'static'],
        ['key' => 'common.search_button', 'label' => 'Nhãn Tìm', 'group' => 'static'],
    ];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $heroVisualSlides = $homeData['hero_slides'] ?? [];
    $primaryHeroSlide = $heroVisualSlides[0] ?? [];
    $defaultBootstrapHeroSlides = [
        [
            'image' => asset('theme-demo/curated/transport/buses/bus-01.jpg').'?v=ser0101-bootstrap-02',
            'alt' => 'Banner bus service 01',
        ],
        [
            'image' => asset('theme-demo/curated/transport/buses/bus-02.jpg').'?v=ser0101-bootstrap-02',
            'alt' => 'Banner bus service 02',
        ],
    ];
    $bootstrapHeroSlides = collect($heroVisualSlides)
        ->take(2)
        ->values()
        ->map(function ($slide, $index) use ($defaultBootstrapHeroSlides) {
            $fallback = $defaultBootstrapHeroSlides[$index] ?? $defaultBootstrapHeroSlides[0];
            $image = (string) ($slide['image'] ?? '');
            $normalizedImage = $image;

            if ($normalizedImage === '' || \Illuminate\Support\Str::endsWith(\Illuminate\Support\Str::before($normalizedImage, '?'), '.svg')) {
                $normalizedImage = $fallback['image'];
            }

            return [
                'image' => $normalizedImage,
                'alt' => (string) ($slide['alt'] ?? $fallback['alt']),
            ];
        })
        ->filter(fn (array $slide): bool => $slide['image'] !== '')
        ->values()
        ->all();
    $heroRouteBadges = collect($serviceRoutes)->pluck('title')->filter()->take(4)->values()->all();

    if ($heroRouteBadges === []) {
        $heroRouteBadges = ['Đón sân bay', 'City transfer', 'Thuê ngày', 'Corporate shuttle'];
    }

    if ($bootstrapHeroSlides === []) {
        $bootstrapHeroSlides = $defaultBootstrapHeroSlides;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'SER0101 Service Transport')) }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <style>
            @include('theme-ser0101::partials.shell-styles')

            :root {
                --ser-navy: #0f172f;
                --ser-night: #08111f;
                --ser-petrol: #0f766e;
                --ser-ink: #18324a;
                --ser-orange: #b45309;
                --ser-amber: #f0b429;
                --ser-sand: #f6efe7;
                --ser-mist: #eef6f4;
                --ser-line: #d6e2de;
                --ser-white: #ffffff;
                --ser-muted: #5d7288;
                --ser-shadow: 0 28px 70px rgba(10, 30, 47, 0.12);
            }

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                color: var(--ser-ink);
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.18), transparent 30%),
                    linear-gradient(180deg, #fbfcfc 0%, #f1f6f3 40%, #fbf8f3 100%);
            }

            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .topbar { background: var(--ser-night); color: #d9e2ec; border-bottom: 1px solid rgba(255, 255, 255, 0.08); }
            .topbar-inner, .header-inner, .nav-inner, .footer-inner, .cta-strip { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
            .topbar-inner { padding: 10px 0; flex-wrap: wrap; font-size: 13px; }
            .inline { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
            .inline button { border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .status-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: color-mix(in srgb, var(--ser-amber) 12%, transparent); color: color-mix(in srgb, var(--ser-amber) 66%, white); font-weight: 700; }
            .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ser-amber); box-shadow: 0 0 0 4px color-mix(in srgb, var(--ser-amber) 16%, transparent); }

            .header-shell { position: sticky; top: 0; z-index: 20; backdrop-filter: blur(18px); background: rgba(248, 251, 253, 0.92); border-bottom: 1px solid rgba(217, 226, 236, 0.95); }
            .header-inner { padding: 16px 0; flex-wrap: wrap; }
            .brand { display: flex; align-items: center; gap: 14px; }
            .brand img { width: 150px; height: 48px; object-fit: contain; }
            .brand-copy strong { display: block; font-size: 18px; color: var(--ser-night); }
            .brand-copy span { display: block; margin-top: 2px; font-size: 13px; color: var(--ser-muted); }
            .brand-meta { display: inline-flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 16px; background: var(--ser-white); border: 1px solid var(--ser-line); color: var(--ser-muted); font-size: 13px; }
            .search { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 58px; max-width: 600px; border: 2px solid color-mix(in srgb, var(--ser-petrol) 22%, transparent); border-radius: 999px; overflow: hidden; background: var(--ser-white); box-shadow: 0 10px 30px color-mix(in srgb, var(--ser-petrol) 8%, transparent); }
            .search input, .search button { border: 0; height: 48px; font-size: 14px; }
            .search input { padding: 0 18px; background: transparent; }
            .search button { background: linear-gradient(135deg, var(--ser-petrol), color-mix(in srgb, var(--ser-petrol) 76%, black)); color: #fff; font-weight: 800; cursor: pointer; }

            .nav { background: rgba(248, 251, 253, 0.88); }
            .nav-inner { padding: 12px 0 16px; flex-wrap: wrap; }
            .nav-menu { display: flex; gap: 20px; flex-wrap: wrap; color: #334e68; font-size: 14px; font-weight: 700; }
            .nav-menu a { position: relative; padding-bottom: 4px; }
            .nav-menu a::after { content: ''; position: absolute; left: 0; bottom: 0; width: 100%; height: 2px; background: linear-gradient(90deg, var(--ser-amber), transparent); transform: scaleX(0); transform-origin: left; transition: transform 0.2s ease; }
            .nav-menu a:hover::after { transform: scaleX(1); }
            .nav-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .nav-cart { padding: 10px 14px; border-radius: 999px; background: rgba(16, 42, 67, 0.06); color: var(--ser-night); font-weight: 700; }
            .nav-cta { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; background: linear-gradient(135deg, var(--ser-orange), color-mix(in srgb, var(--ser-orange) 70%, black)); color: #fff; font-weight: 800; box-shadow: 0 14px 30px color-mix(in srgb, var(--ser-orange) 24%, transparent); }

            .hero { padding: 24px 0 8px; }
            .hero-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
            .hero-card, .metric, .service-card, .service-item, .service-highlight, .service-post, .route-board, .footer-card {
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: var(--ser-shadow);
            }
            .hero-card {
                position: relative;
                overflow: visible;
                min-height: 0;
                padding: 0;
                display: block;
                background: transparent;
                border: 0;
                box-shadow: none;
                color: #fff;
            }
            .hero-visual {
                position: relative;
                z-index: 1;
            }
            .hero h1 { margin: 18px 0 14px; font-size: clamp(36px, 5.5vw, 64px); line-height: 0.98; letter-spacing: -0.03em; max-width: 760px; }
            .hero p { max-width: 640px; margin: 0; color: #d9e2ec; font-size: 16px; line-height: 1.85; }
            .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
            .btn-primary, .btn-secondary { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 18px; border-radius: 999px; font-weight: 800; }
            .btn-primary { background: #fff; color: var(--ser-night); }
            .btn-secondary { background: transparent; color: #fff; border: 1px solid rgba(255, 255, 255, 0.26); }
            .route-strip { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 28px; }
            .route-strip span { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); color: #f8fafc; font-size: 13px; font-weight: 700; }
            .hero-stage {
                position: relative;
                min-height: 620px;
                border-radius: 32px;
                overflow: hidden;
                isolation: isolate;
                border: 1px solid rgba(217, 226, 236, 0.92);
                box-shadow: var(--ser-shadow);
                background: #dfe7ea;
            }
            .ser-hero-carousel,
            .ser-hero-carousel .carousel-inner,
            .ser-hero-carousel .carousel-item {
                min-height: 620px;
                height: 620px;
            }
            .ser-hero-carousel .carousel-item {
                background: #dfe7ea;
            }
            .ser-hero-carousel .carousel-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
            }
            .ser-hero-carousel .carousel-indicators {
                margin-bottom: 1.5rem;
            }
            .ser-hero-carousel .carousel-indicators [data-bs-target] {
                width: 34px;
                height: 6px;
                border: 0;
                border-radius: 999px;
                opacity: 0.48;
                background-color: rgba(255, 255, 255, 0.85);
            }
            .ser-hero-carousel .carousel-indicators .active {
                opacity: 1;
                background-color: var(--ser-amber);
            }
            .ser-hero-carousel .carousel-control-prev,
            .ser-hero-carousel .carousel-control-next {
                width: 62px;
                opacity: 1;
            }
            .ser-hero-carousel .carousel-control-prev-icon,
            .ser-hero-carousel .carousel-control-next-icon {
                width: 3rem;
                height: 3rem;
                border-radius: 999px;
                background-color: rgba(8, 17, 31, 0.5);
                background-size: 44% 44%;
            }

            .section-head h2, .footer-card h4 { margin: 0 0 12px; color: var(--ser-night); }
            .section-head-main { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; min-height: 32px; padding-right: 98px; }
            .section-head-main > div:first-child { min-width: 0; }
            .metric-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 32px; margin-bottom: 10px; padding-right: 92px; }
            .hero-edit-actions, .footer-card-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; min-height: 32px; margin-bottom: 14px; padding-right: 98px; }
            .footer-card-head { align-items: center; }
            .footer-card-head h4 { margin: 0; color: #f8fafc; }
            .hero-edit-actions .sf-inline-edit-btn,
            .footer-card-head .sf-inline-edit-btn,
            .section-head-main .sf-inline-edit-btn,
            .metric-head .sf-inline-edit-btn {
                position: absolute;
                top: 0;
                right: 0;
                z-index: 3;
                flex: 0 0 auto;
                white-space: nowrap;
            }
            .quick-list { display: grid; gap: 12px; }
            .quick-list a {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 16px;
                border-radius: 18px;
                background: linear-gradient(180deg, rgba(255, 247, 237, 0.95), rgba(247, 243, 236, 0.95));
                border: 1px solid rgba(194, 65, 12, 0.08);
                color: var(--ser-night);
                font-weight: 700;
            }
            .quick-list strong { color: var(--ser-orange); }

            .metrics { padding: 14px 0 0; }
            .metrics-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .metric { position: relative; overflow: hidden; padding: 22px; }
            .metric::after { content: ''; position: absolute; inset: auto -30px -40px auto; width: 120px; height: 120px; border-radius: 50%; background: color-mix(in srgb, var(--ser-petrol) 6%, transparent); }
            .metric strong { display: block; position: relative; z-index: 1; font-size: 34px; color: var(--ser-orange); }
            .metric span { display: block; position: relative; z-index: 1; margin-top: 10px; color: var(--ser-muted); line-height: 1.7; }

            .section { padding: 34px 0 0; }
            .section-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 18px; flex-wrap: wrap; }
            .section-head h2 { font-size: 32px; }
            .section-head p { max-width: 700px; margin: 0; color: var(--ser-muted); line-height: 1.8; }
            .section-kicker { display: inline-flex; margin-bottom: 10px; padding: 7px 12px; border-radius: 999px; background: color-mix(in srgb, var(--ser-petrol) 8%, white); color: var(--ser-petrol); font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }

            .service-grid, .card-grid, .highlight-grid, .post-grid { display: grid; gap: 18px; }
            .service-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .service-card, .service-item, .service-highlight, .service-post { padding: 22px; }
            .service-card { position: relative; overflow: hidden; }
            .service-card::before {
                content: '';
                position: absolute;
                inset: 0 0 auto 0;
                height: 5px;
                background: linear-gradient(90deg, var(--ser-petrol), var(--ser-amber), var(--ser-orange));
            }
            .service-card h3 {
                margin: 0 0 12px;
                color: #082f49;
                font-size: 28px;
                font-weight: 800;
                line-height: 1.2;
                letter-spacing: -0.02em;
            }
            .service-item h3, .service-post h3, .service-highlight h3 { margin: 0 0 10px; color: var(--ser-night); }
            .service-card p, .service-item p, .service-post p, .service-highlight p { margin: 0; color: var(--ser-muted); line-height: 1.75; }
            .service-links { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
            .service-links a { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--ser-petrol) 8%, white); color: var(--ser-petrol); font-size: 13px; font-weight: 700; }
            .card-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .service-item-media, .service-post-media { display: block; border-radius: 20px; overflow: hidden; margin-bottom: 16px; }
            .service-item img, .service-post img { width: 100%; aspect-ratio: 16/10; object-fit: cover; border-radius: 20px; background: #edf2f7; transition: transform .25s ease; }
            .service-item-media:hover img, .service-post-media:hover img { transform: scale(1.03); }
            .service-tag { display: inline-flex; margin-bottom: 12px; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--ser-petrol) 8%, white); color: var(--ser-petrol); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
            .service-price { margin-top: 16px; display: flex; justify-content: flex-start; align-items: center; gap: 12px; }
            .service-price strong { font-size: 26px; color: var(--ser-orange); }
            .featured-deal-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 14px;
            }
            .featured-deal-card {
                position: relative;
                overflow: hidden;
                border-radius: 2px;
                border: 1px solid #d9dee6;
                background: #fff;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
                padding: 0;
                transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            }
            .featured-deal-card:hover {
                transform: translateY(-4px);
                border-color: rgba(255, 59, 48, 0.28);
                box-shadow: 0 18px 38px rgba(255, 59, 48, 0.14);
            }
            .featured-deal-media {
                position: relative;
                display: block;
                margin: 0;
                overflow: hidden;
                background: #e8edf3;
            }
            .featured-deal-media img {
                width: 100%;
                aspect-ratio: 1 / 1;
                object-fit: cover;
                border-radius: 0;
                transition: transform .28s ease;
            }
            .featured-deal-card:hover .featured-deal-media img {
                transform: scale(1.04);
            }
            .featured-deal-corner-icon,
            .featured-deal-ribbon,
            .featured-deal-chip,
            .featured-deal-format {
                position: absolute;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
            }
            .featured-deal-corner-icon {
                top: 10px;
                left: 10px;
                width: 34px;
                height: 34px;
                border-radius: 999px;
                background: rgba(17, 24, 39, 0.82);
                color: #fff;
                font-size: 15px;
                box-shadow: 0 8px 16px rgba(15, 23, 42, 0.18);
            }
            .featured-deal-ribbon {
                top: 12px;
                right: -34px;
                width: 132px;
                height: 28px;
                transform: rotate(38deg);
                background: linear-gradient(90deg, #ff3b30, #d70015);
                color: #fff;
                font-size: 12px;
                letter-spacing: .03em;
                text-transform: uppercase;
                box-shadow: 0 10px 20px rgba(215, 0, 21, 0.25);
            }
            .featured-deal-chip {
                left: 10px;
                bottom: 10px;
                padding: 6px 10px;
                border-radius: 4px;
                background: rgba(17, 24, 39, 0.82);
                color: #fff;
                font-size: 12px;
            }
            .featured-deal-format {
                right: 10px;
                bottom: 10px;
                padding: 6px 10px;
                border-radius: 4px;
                background: rgba(255, 255, 255, 0.96);
                color: #374151;
                font-size: 11px;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
            }
            .featured-deal-body {
                padding: 12px 14px 12px;
            }
            .featured-deal-title {
                display: -webkit-box;
                margin: 0;
                height: calc(1.38em * 2);
                overflow: hidden;
                color: #1b2430;
                font-size: 15px;
                font-weight: 700;
                line-height: 1.38;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
            }
            .featured-deal-title a {
                color: inherit;
            }
            .featured-deal-title a:hover {
                color: #b91c1c;
            }
            .featured-deal-divider {
                margin: 10px 0 10px;
                border-top: 1px solid #e5e7eb;
            }
            .featured-deal-pricing {
                display: grid;
                gap: 5px;
            }
            .featured-deal-current {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .featured-deal-current strong {
                color: #ff2b2b;
                font-size: 16px;
                font-weight: 800;
                line-height: 1;
            }
            .featured-deal-discount {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 22px;
                padding: 0 8px;
                border-radius: 6px;
                background: linear-gradient(180deg, #ff4949, #ff2b2b);
                color: #fff;
                font-size: 12px;
                font-weight: 800;
            }
            .featured-deal-original {
                color: #9ca3af;
                font-size: 13px;
                text-decoration: line-through;
            }
            .featured-deal-meta {
                display: none;
            }

            .route-board { overflow: hidden; }
            .route-board-header { display: flex; justify-content: space-between; gap: 16px; padding: 18px 20px; background: linear-gradient(90deg, rgba(16, 42, 67, 0.98), color-mix(in srgb, var(--ser-petrol) 90%, transparent)); color: #fff; flex-wrap: wrap; }
            .route-board-header strong { display: block; margin-bottom: 6px; font-size: 20px; }
            .route-board-header span { color: #d9e2ec; }
            .route-board-meta { display: flex; gap: 10px; flex-wrap: wrap; }
            .route-board-meta span { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 13px; font-weight: 700; }
            .route-table { overflow: auto; }
            .route-table table { width: 100%; border-collapse: collapse; background: var(--ser-white); }
            .route-table th, .route-table td { padding: 18px 20px; text-align: left; border-bottom: 1px solid rgba(217, 226, 236, 0.92); }
            .route-table th { background: #f5f9fb; color: var(--ser-night); font-size: 13px; text-transform: uppercase; letter-spacing: 0.06em; }
            .route-table th:last-child { text-align: right; }
            .route-table td:last-child { text-align: right; color: var(--ser-orange); font-weight: 800; }
            .route-table tr:hover td { background: rgba(247, 243, 236, 0.7); }
            .route-table tr:last-child td { border-bottom: 0; }

            .highlight-grid, .post-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .highlight-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; margin-bottom: 16px; border-radius: 18px; background: linear-gradient(135deg, color-mix(in srgb, var(--ser-petrol) 14%, white), color-mix(in srgb, var(--ser-amber) 18%, white)); color: var(--ser-night); font-size: 22px; font-weight: 800; }
            .service-post {
                position: relative;
                overflow: hidden;
                border: 1px solid rgba(214, 226, 237, 0.96);
                box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
            }
            .service-post::before {
                content: '';
                position: absolute;
                inset: 0 auto auto 0;
                width: 100%;
                height: 4px;
                background: linear-gradient(90deg, color-mix(in srgb, var(--ser-petrol) 90%, transparent), color-mix(in srgb, var(--ser-orange) 90%, transparent));
            }
            .service-post h3 {
                margin-top: 4px;
                font-size: 27px;
                font-weight: 800;
                line-height: 1.35;
                letter-spacing: -0.02em;
            }
            .service-post h3 a {
                display: inline-block;
                font-weight: inherit;
                color: var(--ser-night);
                text-decoration: none;
                text-wrap: balance;
                transition: color .18s ease, text-shadow .18s ease;
            }
            .service-post h3 a:hover {
                color: var(--ser-petrol);
                text-shadow: 0 10px 24px color-mix(in srgb, var(--ser-petrol) 14%, transparent);
            }

            .cta-section { padding: 36px 0 0; }
            .cta-strip {
                padding: 22px 24px;
                border-radius: 30px;
                background: linear-gradient(135deg, rgba(16, 42, 67, 0.98), color-mix(in srgb, var(--ser-petrol) 92%, transparent));
                color: #fff;
                box-shadow: var(--ser-shadow);
                flex-wrap: wrap;
            }
            .cta-strip strong { display: block; margin-bottom: 8px; font-size: 24px; }
            .cta-strip p { margin: 0; max-width: 640px; color: #d9e2ec; line-height: 1.75; }
            .cta-actions { display: flex; gap: 12px; flex-wrap: wrap; }

            .footer { margin-top: 36px; background: linear-gradient(180deg, var(--ser-night), #071421); color: #d9e2ec; }
            .footer-inner { padding: 32px 0 40px; align-items: flex-start; }
            .footer-grid { display: grid; grid-template-columns: 1.1fr 1fr 1fr; gap: 24px; width: 100%; }
            .footer-card { padding: 22px; background: rgba(255, 255, 255, 0.04); border-color: rgba(255, 255, 255, 0.08); box-shadow: none; }
            .footer-card p, .footer-card a { color: #bcccdc; line-height: 1.8; }
            .footer-card strong { display: block; margin-bottom: 10px; color: #fff; }

            @media (max-width: 980px) {
                .service-grid, .card-grid, .highlight-grid, .post-grid, .metrics-grid, .footer-grid { grid-template-columns: 1fr 1fr; }
                .featured-deal-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .hero-grid { grid-template-columns: 1fr; }
                .hero-card { min-height: 0; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .header-inner, .nav-inner, .footer-inner, .topbar-inner, .cta-strip { align-items: flex-start; flex-direction: column; }
                .search { width: 100%; max-width: none; grid-template-columns: 1fr; border-radius: 22px; }
                .service-grid, .card-grid, .highlight-grid, .post-grid, .metrics-grid, .footer-grid { grid-template-columns: 1fr; }
                .featured-deal-grid { grid-template-columns: 1fr; }
                .hero-card { grid-template-columns: 1fr; }
                .hero-stage,
                .ser-hero-carousel,
                .ser-hero-carousel .carousel-inner,
                .ser-hero-carousel .carousel-item { min-height: 420px; height: 420px; }
                .route-table th, .route-table td { padding: 14px 16px; }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            @if (isset($landingBlocks) && is_array($landingPage ?? null))
                @include('partials.configurable-landing-blocks')
            @else
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-card">
                        <div class="hero-stage">
                            <div id="ser0101HeroCarousel" class="carousel slide carousel-fade ser-hero-carousel" data-bs-ride="carousel" data-bs-interval="4200">
                                <div class="carousel-indicators">
                                    @foreach ($bootstrapHeroSlides as $slide)
                                        <button
                                            type="button"
                                            data-bs-target="#ser0101HeroCarousel"
                                            data-bs-slide-to="{{ $loop->index }}"
                                            class="{{ $loop->first ? 'active' : '' }}"
                                            aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                            aria-label="Slide {{ $loop->iteration }}"
                                        ></button>
                                    @endforeach
                                </div>
                                <div class="carousel-inner">
                                    @foreach ($bootstrapHeroSlides as $slide)
                                        <div class="carousel-item {{ $loop->first ? 'active' : '' }}">
                                            <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] }}">
                                        </div>
                                    @endforeach
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#ser0101HeroCarousel" data-bs-slide="prev" aria-label="Slide trước">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#ser0101HeroCarousel" data-bs-slide="next" aria-label="Slide tiếp theo">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @if ($serviceMetrics !== [])
                <section class="metrics">
                    <div class="metrics-grid">
                        @foreach ($serviceMetrics as $index => $metric)
                            @php
                                $metricEditFields = [
                                    [
                                        'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.value', $index)),
                                        'label' => 'Giá trị',
                                    ],
                                    [
                                        'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.suffix', $index)),
                                        'label' => 'Hậu tố',
                                    ],
                                    [
                                        'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.label', $index)),
                                        'label' => 'Nhãn',
                                    ],
                                ];
                            @endphp
                            <article class="metric">
                                <div class="metric-head">
                                    <span></span>
                                    @if ($canQuickEditThemeBlocks)
                                        <button
                                            type="button"
                                            class="sf-inline-edit-btn"
                                            data-sf-inline-edit-trigger
                                            data-edit-title="Sửa chỉ số {{ $index + 1 }}"
                                            data-edit-fields='@json($metricEditFields)'
                                        >
                                            Sửa chỉ số
                                        </button>
                                    @endif
                                </div>
                                <strong><span data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.value', $index)) }}">{{ $metric['value'] ?? 0 }}</span><span data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.suffix', $index)) }}">{{ $metric['suffix'] ?? '' }}</span></strong>
                                <span data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, sprintf('service_metrics.%d.label', $index)) }}">{{ $metric['label'] ?? '' }}</span>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="section">
                <div class="section-head">
                    <div>
                        <span class="section-kicker">{{ $t('home.service_groups_kicker', 'Nhóm xe và dịch vụ') }}</span>
                        <h2>{{ $t('home.service_groups_title', 'Nhóm dịch vụ và tuyến khai thác') }}</h2>
                        <p>{{ $t('home.service_groups_summary', 'Catalog hiện tại được map lại thành danh mục nhà xe, shuttle doanh nghiệp và gói tuyến để có thể đổi theme mà không thay đổi schema.') }}</p>
                    </div>
                </div>
                <div class="service-grid">
                    @foreach ($serviceCategories as $category)
                        <article class="service-card">
                            <h3><a href="{{ $category['url'] ?? '#' }}">{{ $category['name'] ?? '' }}</a></h3>
                            @if (!empty($category['summary']))
                                <p>{{ $category['summary'] }}</p>
                            @endif
                            <div class="service-links">
                                @foreach ($category['children'] ?? [] as $child)
                                    <a href="{{ $child['url'] ?? '#' }}">{{ $child['name'] ?? '' }}</a>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="section" id="featured-services">
                <div class="section-head">
                    <div>
                        <span class="section-kicker">{{ $t('home.featured_kicker', 'Gói tuyến nổi bật') }}</span>
                        <h2>{{ $homeData['featured_title'] ?? $t('theme.fallback.featured_services', 'Gói dịch vụ nổi bật') }}</h2>
                        <p>{{ $t('home.featured_summary', 'Vận dụng CatalogProduct để đại diện cho gói shuttle, tour coach và tuyến hợp đồng, nhưng bố cục ưu tiên chuyển đổi theo phong cách nhà xe.') }}</p>
                    </div>
                </div>
                <div class="featured-deal-grid">
                    @foreach ($featuredServices as $service)
                        @php
                            $currentPrice = (int) ($service['price'] ?? 0);
                            $discountRate = 10 + (($loop->index % 4) * 8);
                            $originalPrice = $currentPrice > 0
                                ? (int) ceil($currentPrice / (1 - ($discountRate / 100)))
                                : null;
                            $dealMeta = $service['tag'] ?? $t('product.service_tag_default', 'Gói dịch vụ');
                        @endphp
                        <article class="featured-deal-card">
                            <a class="featured-deal-media" href="{{ $service['url'] ?? '#' }}" aria-label="{{ $service['title'] ?? '' }}">
                                <img src="{{ $service['image'] ?? 'https://picsum.photos/seed/ser0101-service/960/720' }}" alt="{{ $service['title'] ?? '' }}">
                                <span class="featured-deal-corner-icon">★</span>
                                <span class="featured-deal-ribbon">Độc quyền</span>
                                <span class="featured-deal-chip">{{ $dealMeta }}</span>
                                <span class="featured-deal-format">E-Voucher</span>
                            </a>
                            <div class="featured-deal-body">
                                <h3 class="featured-deal-title"><a href="{{ $service['url'] ?? '#' }}">{{ $service['title'] ?? '' }}</a></h3>
                                <div class="featured-deal-divider"></div>
                                <div class="featured-deal-pricing">
                                    <div class="featured-deal-current">
                                        <strong>{{ $formatCurrency($service['price'] ?? null) }}</strong>
                                        @if ($currentPrice > 0)
                                            <span class="featured-deal-discount">-{{ $discountRate }}%</span>
                                        @endif
                                    </div>
                                    @if ($originalPrice !== null)
                                        <span class="featured-deal-original">{{ $formatCurrency($originalPrice) }}</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($serviceRoutes !== [])
                <section class="section">
                    <div class="section-head">
                        <div>
                            <span class="section-kicker">{{ $t('home.route_board_kicker', 'Bảng giá tham khảo') }}</span>
                            <h2>{{ $t('home.route_board_title', 'Tuyến và gói tham khảo') }}</h2>
                            <p>{{ $t('home.route_board_summary', 'Bảng tuyến giữ logic dữ liệu của catalog nhưng trình bày theo cách điều phối viên cần: tên gói, nhóm xe và mức giá.') }}</p>
                        </div>
                    </div>
                    <div class="route-board">
                        <div class="route-board-header">
                            <div>
                                <strong>{{ $t('home.route_board_header_title', 'Bảng tuyến nổi bật') }}</strong>
                                <span>{{ $t('home.route_board_header_summary', 'Tập trung nhu cầu đi sân bay, đưa đón doanh nghiệp, hợp đồng tour và chuyến hàng nhẹ.') }}</span>
                            </div>
                        </div>
                        <div class="route-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>{{ $t('home.route_table_service', 'Gói dịch vụ') }}</th>
                                        <th>{{ $t('home.route_table_group', 'Nhóm / ghi chú') }}</th>
                                        <th>{{ $t('home.route_table_price', 'Mức giá') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($serviceRoutes as $route)
                                        <tr>
                                            <td><a href="{{ $route['url'] ?? '#' }}">{{ $route['title'] ?? '' }}</a></td>
                                            <td>{{ $route['summary'] ?? '' }}</td>
                                            <td>{{ $formatCurrency($route['price'] ?? null) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @endif

            @if ($serviceHighlights !== [])
                <section class="section">
                    <div class="section-head">
                        <div>
                            <span class="section-kicker">{{ $t('home.highlights_kicker', 'Vì sao chọn theme này') }}</span>
                            <h2>{{ $t('home.highlights_title', 'Điểm nhấn của SER0101') }}</h2>
                            <p>{{ $t('home.highlights_summary', 'Theme service-first dời trọng tâm từ trưng bày sản phẩm sang hotline, bảng tuyến, nội dung tạo tin cậy và các khung CTA có thể dùng lại trên toàn bộ hành trình.') }}</p>
                        </div>
                    </div>
                    <div class="highlight-grid">
                        @foreach ($serviceHighlights as $index => $highlight)
                            <article class="service-highlight">
                                <div class="highlight-icon">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                                <h3>{{ $highlight['title'] ?? '' }}</h3>
                                <p>{{ $highlight['summary'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($latestPosts !== [])
                <section class="section">
                    <div class="section-head">
                        <div class="section-head-main">
                            <div>
                                <span class="section-kicker" data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, 'latest_posts.kicker') }}">{{ $latestPostsSection['kicker'] ?? 'Tin mới' }}</span>
                                <h2 data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, 'latest_posts.title') }}">{{ $latestPostsSection['title'] ?? 'Tin mới' }}</h2>
                                <p data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, 'latest_posts.summary') }}">{{ $latestPostsSection['summary'] ?? 'Khối này lấy bài viết mới nhất từ CMS. Sếp có thể đổi đoạn mô tả này trong phần nội dung website để phù hợp với từng preset hoặc thương hiệu.' }}</p>
                            </div>
                            @if ($canQuickEditThemeBlocks)
                                <button
                                    type="button"
                                    class="sf-inline-edit-btn"
                                    data-sf-inline-edit-trigger
                                    data-edit-title="Sửa block Tin mới"
                                    data-edit-fields='@json($latestPostsSectionEditFields)'
                                >
                                    Sửa block
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="post-grid">
                        @foreach ($latestPosts as $post)
                            <article class="service-post">
                                <img src="{{ $post['image'] ?: 'https://picsum.photos/seed/ser0101-post/960/720' }}" alt="{{ $post['title'] ?? '' }}">
                                <h3><a href="{{ $post['url'] ?? '#' }}">{{ $post['title'] ?? '' }}</a></h3>
                                <p>{{ $post['excerpt'] ?? '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="cta-section">
                <div class="cta-strip">
                    <div>
                        <strong>{{ $t('home.cta_title', 'Cần chốt lịch xe nhanh cho hôm nay?') }}</strong>
                        <p>{{ $t('home.cta_summary', 'Gọi hotline để điều phối viên gom thông tin tuyến, loại xe và số khách. Sau đó khách có thể lưu danh sách nhu cầu hoặc gửi yêu cầu tại bước checkout.') }}</p>
                    </div>
                    <div class="cta-actions">
                        <a class="btn-primary" href="tel:{{ preg_replace('/\D+/', '', $contactHotline) }}">{{ $contactHotline }}</a>
                        <a class="btn-secondary" href="{{ route('site.catalog.search') }}">{{ $t('home.cta_secondary', 'Tìm tuyến phù hợp') }}</a>
                    </div>
                </div>
            </section>
            @endif
        </main>

        <footer class="footer">
            <div class="wrap footer-inner">
                <div class="footer-grid">
                    <section class="footer-card">
                        <div class="footer-card-head">
                            <h4>{{ data_get($branding, 'company_name', 'SER0101') }}</h4>
                            @if ($canQuickEditThemeBlocks)
                                <button
                                    type="button"
                                    class="sf-inline-edit-btn"
                                    data-sf-inline-edit-trigger
                                    data-edit-title="Sửa footer SER0101"
                                    data-edit-fields='@json($footerEditFields)'
                                >
                                    Sửa footer
                                </button>
                            @endif
                        </div>
                        <p data-translation-display="home.footer_summary">{{ $t('home.footer_summary', 'Theme service-first cho nhà xe, shuttle doanh nghiệp và vận chuyển hàng nhẹ. Ưu tiên hotline, báo giá nhanh và nội dung tạo tin cậy.') }}</p>
                        @include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'ser-footer-boc-status'])
                    </section>
                    <section class="footer-card">
                        <h4 data-translation-display="home.footer_contact_title">{{ $t('home.footer_contact_title', 'Liên hệ') }}</h4>
                        <strong>{{ $contactHotline }}</strong>
                        <p>{{ $contactEmail }}</p>
                        <p>{{ $contactLocation }}</p>
                    </section>
                    <section class="footer-card">
                        <h4 data-translation-display="home.footer_nav_title">{{ $t('home.footer_nav_title', 'Điều hướng nhanh') }}</h4>
                        <a href="{{ route('site.home') }}" data-translation-display="common.home">{{ $t('common.home', 'Trang chủ') }}</a><br>
                        <a href="{{ route('site.blog.index') }}" data-translation-display="menu.default.blog">{{ $t('menu.default.blog', 'Cẩm nang') }}</a><br>
                        <a href="{{ route('site.catalog.search') }}" data-translation-display="common.search_button">{{ $t('common.search_button', 'Tìm') }}</a>
                    </section>
                </div>
            </div>
        </footer>

        @include('theme-ser0101::partials.product-search-autocomplete')
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        @if ($canQuickEditThemeBlocks)
            @include('partials.storefront-inline-translation-editor', [
                'editorId' => 'ser0101-inline-editor',
                'themeKey' => $themeKey,
                'currentLocale' => app()->getLocale(),
                'supportedLocales' => $quickEditLocales,
                'localeOptions' => $quickEditLocaleOptions,
            ])
        @endif

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
