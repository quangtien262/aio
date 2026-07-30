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
    $themeKey = 'SER0100';
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
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
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $heroVisualSlides = $homeData['hero_slides'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'SER0100 Service Transport')) }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0100::partials.shell-styles')

            :root {
                --ser-navy: #102a43;
                --ser-night: #0b1f33;
                --ser-petrol: #1f6f78;
                --ser-ink: #243b53;
                --ser-orange: #c2410c;
                --ser-amber: #f59e0b;
                --ser-sand: #f7f3ec;
                --ser-mist: #eef5f7;
                --ser-line: #d9e2ec;
                --ser-white: #ffffff;
                --ser-muted: #627d98;
                --ser-shadow: 0 24px 60px rgba(15, 42, 67, 0.12);
            }

            @include('theme-ser0100::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                color: var(--ser-ink);
                background:
                    radial-gradient(circle at top right, rgba(245, 158, 11, 0.15), transparent 28%),
                    linear-gradient(180deg, #f7fbfd 0%, #edf4f7 42%, #f8fbfd 100%);
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
            .nav-cta { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; background: linear-gradient(135deg, var(--ser-orange), var(--o-deep)); color: #fff; font-weight: 800; box-shadow: 0 14px 30px color-mix(in srgb, var(--ser-orange) 24%, transparent); }

            .hero { padding: 24px 0 8px; }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.18fr) minmax(340px, 0.82fr); gap: 20px; }
            .hero-card, .hero-side-card, .metric, .service-card, .service-item, .service-highlight, .service-post, .route-board, .footer-card {
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: var(--ser-shadow);
            }
            .hero-card {
                position: relative;
                overflow: hidden;
                min-height: 0;
                padding: 14px;
                display: block;
                background:
                    radial-gradient(circle at top right, color-mix(in srgb, var(--ser-amber) 18%, transparent), transparent 30%),
                    linear-gradient(135deg, rgba(10, 24, 38, 0.92), rgba(16, 42, 67, 0.88) 54%, color-mix(in srgb, var(--ser-petrol) 84%, transparent));
                color: #fff;
            }
            .hero-card::before {
                content: '';
                position: absolute;
                inset: -120px auto auto -120px;
                width: 280px;
                height: 280px;
                background: radial-gradient(circle, rgba(255, 255, 255, 0.12), transparent 68%);
                border-radius: 50%;
            }
            .hero-card::after {
                content: '';
                position: absolute;
                inset: auto -70px -96px auto;
                width: 280px;
                height: 280px;
                border-radius: 50%;
                background: radial-gradient(circle, color-mix(in srgb, var(--ser-amber) 20%, transparent), transparent 72%);
            }
            .hero-visual {
                position: relative;
                z-index: 1;
            }
            .eyebrow { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .eyebrow::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--ser-amber); box-shadow: 0 0 0 6px color-mix(in srgb, var(--ser-amber) 18%, transparent); }
            .hero h1 { margin: 18px 0 14px; font-size: clamp(36px, 5.5vw, 64px); line-height: 0.98; letter-spacing: -0.03em; max-width: 760px; }
            .hero p { max-width: 640px; margin: 0; color: #d9e2ec; font-size: 16px; line-height: 1.85; }
            .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
            .btn-primary, .btn-secondary { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 18px; border-radius: 999px; font-weight: 800; }
            .btn-primary { background: #fff; color: var(--ser-night); }
            .btn-secondary { background: transparent; color: #fff; border: 1px solid rgba(255, 255, 255, 0.26); }
            .route-strip { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 28px; }
            .route-strip span { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); color: #f8fafc; font-size: 13px; font-weight: 700; }
            .hero-badge { margin-top: 24px; display: inline-flex; padding: 12px 16px; border-radius: 18px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.12); font-weight: 700; }
            .hero-visual {
                position: relative;
                display: flex;
                align-self: start;
                height: auto;
                min-height: 0;
                width: 100%;
            }
            .hero-visual-main {
                position: relative;
                flex: 1 1 auto;
                overflow: hidden;
                min-height: 0;
                height: auto;
                border-radius: 28px;
                border: 1px solid rgba(255, 255, 255, 0.08);
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(8, 26, 42, 0.04));
                box-shadow: 0 18px 36px rgba(4, 13, 23, 0.22);
            }
            .hero-visual-main img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .hero-visual-slider {
                position: relative;
                width: 100%;
                height: 100%;
                min-height: 100%;
                isolation: isolate;
            }
            .hero-visual-slide {
                position: absolute;
                inset: 0;
                opacity: 0;
                transform: scale(1.03);
                transition: opacity .45s ease, transform 4.8s ease;
            }
            .hero-visual-slide.is-active {
                opacity: 1;
                transform: scale(1);
            }
            .hero-visual-slide::after {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(7, 20, 33, 0.12) 54%, rgba(7, 20, 33, 0.22)),
                    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.16), transparent 28%);
            }
            .hero-visual-slide img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
                transform: scale(1.01);
                filter: saturate(1.05) contrast(1.01);
                background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.05));
            }
            .hero-visual-slide.is-featured.is-active img {
                animation: serHeroKenBurns 7.2s ease-out both;
                will-change: transform;
            }
            @keyframes serHeroKenBurns {
                0% {
                    transform: scale(1.045) translate3d(-0.8%, -0.5%, 0);
                }
                100% {
                    transform: scale(1.09) translate3d(0.8%, 0.6%, 0);
                }
            }
            .hero-visual-caption {
                position: absolute;
                top: 18px;
                left: 18px;
                z-index: 2;
                max-width: min(280px, calc(100% - 118px));
                padding: 12px 14px;
                border-radius: 18px;
                background: linear-gradient(180deg, rgba(7, 20, 33, 0.5), rgba(7, 20, 33, 0.3));
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                box-shadow: 0 14px 30px rgba(4, 13, 23, 0.16);
                pointer-events: none;
            }
            .hero-visual-caption strong,
            .hero-visual-caption span {
                display: block;
            }
            .hero-visual-caption strong {
                margin-bottom: 8px;
                color: rgba(255, 255, 255, 0.98);
                font-size: 17px;
                line-height: 1.25;
                letter-spacing: -0.02em;
            }
            .hero-visual-caption span:first-child {
                margin-bottom: 6px;
                color: #fdba74;
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .hero-visual-caption span:last-child {
                color: rgba(226, 232, 240, 0.92);
                font-size: 11px;
                line-height: 1.5;
            }
            .hero-visual-nav {
                position: absolute;
                inset: auto 18px 18px auto;
                z-index: 4;
                display: inline-flex;
                gap: 10px;
            }
            .hero-visual-nav button {
                width: 42px;
                height: 42px;
                border: 0;
                border-radius: 999px;
                background: rgba(7, 20, 33, 0.48);
                color: #fff;
                backdrop-filter: blur(10px);
                box-shadow: 0 12px 24px rgba(4, 13, 23, 0.18);
                cursor: pointer;
                transition: transform 0.2s ease, background 0.2s ease;
            }
            .hero-visual-nav button:hover {
                transform: translateY(-1px);
                background: color-mix(in srgb, var(--ser-amber) 88%, transparent);
            }
            .hero-visual-dots {
                position: absolute;
                left: 18px;
                bottom: 18px;
                z-index: 4;
                display: inline-flex;
                gap: 8px;
                padding: 8px 10px;
                border-radius: 999px;
                background: rgba(7, 20, 33, 0.3);
                backdrop-filter: blur(8px);
            }
            .hero-visual-dots button {
                width: 9px;
                height: 9px;
                padding: 0;
                border: 0;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.34);
                cursor: pointer;
            }
            .hero-visual-dots button.is-active {
                width: 24px;
                background: linear-gradient(90deg, var(--ser-amber), color-mix(in srgb, var(--ser-amber) 54%, white));
            }

            .hero-side { display: grid; gap: 18px; align-self: start; }
            .hero-side-card { padding: 22px; height: auto; }
            .hero-side-card h3, .section-head h2, .footer-card h4 { margin: 0 0 12px; color: var(--ser-night); }
            .quote-header { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; min-height: 32px; margin-bottom: 14px; padding-right: 96px; }
            .quote-badge { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--ser-petrol) 10%, white); color: var(--ser-petrol); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; }
            .quote-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            .quote-item { padding: 16px; border-radius: 20px; background: linear-gradient(180deg, #f8fbfd, #eef5f7); border: 1px solid rgba(217, 226, 236, 0.92); }
            .quote-item-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 34px; margin-bottom: 12px; padding-right: 78px; }
            .quote-item-head strong { flex: 1 1 auto; min-width: 0; margin: 0; }
            .quote-item strong { display: block; margin-bottom: 8px; color: var(--ser-night); }
            .quote-item span { display: block; color: var(--ser-muted); line-height: 1.7; font-size: 14px; }
            .section-head-main { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; min-height: 32px; padding-right: 98px; }
            .section-head-main > div:first-child { min-width: 0; }
            .metric-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; min-height: 32px; margin-bottom: 10px; padding-right: 92px; }
            .hero-edit-actions, .footer-card-head { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; min-height: 32px; margin-bottom: 14px; padding-right: 98px; }
            .footer-card-head { align-items: center; }
            .footer-card-head h4 { margin: 0; color: #f8fafc; }
            .quote-header .sf-inline-edit-btn,
            .quote-item-head .sf-inline-edit-btn,
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
            .quote-item-head .sf-inline-edit-btn {
                min-height: 34px;
                padding: 0 10px;
                font-size: 11px;
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
                inset: 0 auto 0 0;
                width: 6px;
                background: linear-gradient(180deg, var(--ser-petrol), var(--ser-orange));
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
                .hero-grid, .service-grid, .card-grid, .highlight-grid, .post-grid, .metrics-grid, .footer-grid { grid-template-columns: 1fr 1fr; }
                .hero-grid { grid-template-columns: 1fr; }
                .hero-card { min-height: 0; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .header-inner, .nav-inner, .footer-inner, .topbar-inner, .cta-strip { align-items: flex-start; flex-direction: column; }
                .search { width: 100%; max-width: none; grid-template-columns: 1fr; border-radius: 22px; }
                .service-grid, .card-grid, .highlight-grid, .post-grid, .metrics-grid, .footer-grid, .quote-grid { grid-template-columns: 1fr; }
                .hero-card { grid-template-columns: 1fr; }
                .hero-card { padding: 24px; }
                .hero-visual-main { min-height: 260px; }
                .hero-visual-caption {
                    top: 14px;
                    left: 14px;
                    max-width: calc(100% - 92px);
                    padding: 12px 14px;
                }
                .hero-visual-caption strong { font-size: 16px; }
                .hero-visual-nav { inset: auto 14px 14px auto; gap: 8px; }
                .hero-visual-nav button { width: 38px; height: 38px; }
                .hero-visual-dots { left: 14px; bottom: 14px; }
                .route-table th, .route-table td { padding: 14px 16px; }
            }
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            @if (isset($landingBlocks) && is_array($landingPage ?? null))
                @include('partials.configurable-landing-blocks')
            @else
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-card">
                        <div class="hero-visual">
                            <div class="hero-visual-main">
                                <div class="hero-visual-slider" data-ser-hero-slider>
                                    @foreach ($heroVisualSlides as $slide)
                                        <figure class="hero-visual-slide {{ $loop->first ? 'is-active is-featured' : '' }}" data-ser-hero-slide>
                                            <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] }}" style="object-position: {{ $slide['position'] ?? 'center' }};">
                                            @if (($slide['show_caption'] ?? true) === true)
                                                <figcaption class="hero-visual-caption">
                                                    <span>{{ $slide['kicker'] ?? '' }}</span>
                                                    <strong>{{ $slide['title'] ?? '' }}</strong>
                                                    <span>{{ $slide['summary'] ?? '' }}</span>
                                                </figcaption>
                                            @endif
                                        </figure>
                                    @endforeach
                                    <div class="hero-visual-nav" aria-label="Điều hướng banner">
                                        <button type="button" data-ser-hero-prev aria-label="Slide trước">&#8249;</button>
                                        <button type="button" data-ser-hero-next aria-label="Slide tiếp theo">&#8250;</button>
                                    </div>
                                    <div class="hero-visual-dots">
                                        @foreach ($heroVisualSlides as $slide)
                                            <button
                                                type="button"
                                                class="{{ $loop->first ? 'is-active' : '' }}"
                                                data-ser-hero-dot
                                                aria-label="Slide {{ $loop->iteration }}"
                                            ></button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-side">
                        <div class="hero-side-card">
                            <div class="quote-header">
                                <span
                                    class="quote-badge"
                                    data-translation-display="{{ $themeBlockRegistry->contentKey('SER0100', 'quote_panel.badge') }}"
                                >{{ $quotePanel['badge'] ?? 'Báo giá trong ngày' }}</span>
                                @if ($canQuickEditThemeBlocks)
                                    <button
                                        type="button"
                                        class="sf-inline-edit-btn"
                                        data-sf-inline-edit-trigger
                                        data-edit-title="Sửa khối Báo giá trong ngày"
                                        data-edit-fields='@json($quotePanelBadgeEditFields)'
                                    >
                                        Sửa khối
                                    </button>
                                @endif
                            </div>
                            <div class="quote-grid">
                                @foreach (($quotePanel['items'] ?? []) as $index => $item)
                                    <div class="quote-item">
                                        <div class="quote-item-head">
                                            <strong data-translation-display="{{ $themeBlockRegistry->contentKey('SER0100', sprintf('quote_panel.items.%d.title', $index)) }}">{{ $item['title'] ?? '' }}</strong>
                                            @if ($canQuickEditThemeBlocks)
                                                @php
                                                    $quoteItemEditFields = [
                                                        [
                                                            'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('quote_panel.items.%d.title', $index)),
                                                            'label' => 'Tiêu đề',
                                                        ],
                                                        [
                                                            'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('quote_panel.items.%d.summary', $index)),
                                                            'label' => 'Mô tả',
                                                        ],
                                                    ];
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="sf-inline-edit-btn"
                                                    data-sf-inline-edit-trigger
                                                    data-edit-title="Sửa item báo giá {{ $index + 1 }}"
                                                    data-edit-fields='@json($quoteItemEditFields)'
                                                >
                                                    Sửa mục
                                                </button>
                                            @endif
                                        </div>
                                        <span data-translation-display="{{ $themeBlockRegistry->contentKey('SER0100', sprintf('quote_panel.items.%d.summary', $index)) }}">{{ $item['summary'] ?? '' }}</span>
                                    </div>
                                @endforeach
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
                <div class="card-grid">
                    @foreach ($featuredServices as $service)
                        <article class="service-item">
                            <a class="service-item-media" href="{{ $service['url'] ?? '#' }}" aria-label="{{ $service['title'] ?? '' }}">
                                <img src="{{ $service['image'] ?? 'https://picsum.photos/seed/ser0100-service/960/720' }}" alt="{{ $service['title'] ?? '' }}">
                            </a>
                            <span class="service-tag">{{ $service['tag'] ?? $t('product.service_tag_default', 'Gói dịch vụ') }}</span>
                            <h3><a href="{{ $service['url'] ?? '#' }}">{{ $service['title'] ?? '' }}</a></h3>
                            <p>{{ \Illuminate\Support\Str::limit((string) ($service['summary'] ?? $service['tag'] ?? ''), 120) }}</p>
                            <div class="service-price">
                                <strong>{{ $formatCurrency($service['price'] ?? null) }}</strong>
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
                            <div class="route-board-meta">
                                <span>{{ $t('home.route_board_meta_cta', 'CTA sẵn cho điều phối') }}</span>
                                <span>{{ $t('home.route_board_meta_lead', 'Ưu tiên thu lead') }}</span>
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
                            <h2>{{ $t('home.highlights_title', 'Điểm nhấn của SER0100') }}</h2>
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
                                <img src="{{ $post['image'] ?: 'https://picsum.photos/seed/ser0100-post/960/720' }}" alt="{{ $post['title'] ?? '' }}">
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
                            <h4>{{ data_get($branding, 'company_name', 'SER0100') }}</h4>
                            @if ($canQuickEditThemeBlocks)
                                <button
                                    type="button"
                                    class="sf-inline-edit-btn"
                                    data-sf-inline-edit-trigger
                                    data-edit-title="Sửa footer SER0100"
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

        @include('theme-ser0100::partials.product-search-autocomplete')
        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        @if ($canQuickEditThemeBlocks)
            @include('partials.storefront-inline-translation-editor', [
                'editorId' => 'ser0100-inline-editor',
                'themeKey' => $themeKey,
                'currentLocale' => app()->getLocale(),
                'supportedLocales' => $quickEditLocales,
                'localeOptions' => $quickEditLocaleOptions,
            ])
        @endif

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const syncHeroVisualHeight = (pass = 0) => {
                    document.querySelectorAll('.hero-grid').forEach((grid) => {
                        const visual = grid.querySelector('.hero-visual-main');
                        const quoteCard = grid.querySelector('.hero-side-card');

                        if (!visual || !quoteCard) {
                            return;
                        }

                        if (window.innerWidth <= 980) {
                            visual.style.height = '';
                            return;
                        }

                        visual.style.height = `${Math.round(quoteCard.getBoundingClientRect().height)}px`;
                    });

                    if (pass < 2) {
                        window.requestAnimationFrame(() => syncHeroVisualHeight(pass + 1));
                    }
                };

                document.querySelectorAll('[data-ser-hero-slider]').forEach((slider) => {
                    const slides = Array.from(slider.querySelectorAll('[data-ser-hero-slide]'));
                    const dots = Array.from(slider.querySelectorAll('[data-ser-hero-dot]'));
                    const prevButton = slider.querySelector('[data-ser-hero-prev]');
                    const nextButton = slider.querySelector('[data-ser-hero-next]');

                    if (slides.length <= 1) {
                        prevButton?.remove();
                        nextButton?.remove();
                        return;
                    }

                    let activeIndex = 0;
                    let intervalId = null;

                    const render = (index) => {
                        activeIndex = (index + slides.length) % slides.length;

                        slides.forEach((slide, slideIndex) => {
                            slide.classList.toggle('is-active', slideIndex === activeIndex);
                        });

                        dots.forEach((dot, dotIndex) => {
                            dot.classList.toggle('is-active', dotIndex === activeIndex);
                        });
                    };

                    const stop = () => {
                        window.clearInterval(intervalId);
                    };

                    const start = () => {
                        stop();
                        intervalId = window.setInterval(() => {
                            render(activeIndex + 1);
                        }, 3800);
                    };

                    dots.forEach((dot, dotIndex) => {
                        dot.addEventListener('click', () => {
                            render(dotIndex);
                            start();
                        });
                    });

                    prevButton?.addEventListener('click', () => {
                        render(activeIndex - 1);
                        start();
                    });

                    nextButton?.addEventListener('click', () => {
                        render(activeIndex + 1);
                        start();
                    });

                    slider.addEventListener('mouseenter', stop);
                    slider.addEventListener('mouseleave', start);

                    render(0);
                    start();
                });

                syncHeroVisualHeight();
                window.addEventListener('resize', () => syncHeroVisualHeight());
            });
        </script>
    </body>
</html>
