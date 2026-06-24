@php
    $themeShellData = $themeShellData ?? [];
    $siteProfile = $themeShellData['site_profile'] ?? $siteProfile ?? \App\Models\SiteProfile::query()->first();
    $branding = $themeShellData['branding'] ?? [];
    $topMenu = $themeShellData['top_menu'] ?? [];
    $productMenu = $themeShellData['product_menu'] ?? [];
    $sidePromos = $themeShellData['side_banners'] ?? [];
    $cartSummary = $themeShellData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $themeShellData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $themeShellData['newsletter'] ?? ['is_subscribed' => false];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('TH0020', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', config('mail.from.address', 'cs@aio.local'));
    $contactLocation = data_get($branding, 'support_location', 'Hà N?i');
    $companyTitle = data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', ''));
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $pageSlug = (string) ($entry->slug ?? '');
    $isAboutPage = ($contentType ?? null) === 'page' && in_array($pageSlug, ['gioi-thieu', 'about'], true);
    $isContactPage = ($contentType ?? null) === 'page' && in_array($pageSlug, ['lien-he', 'contact'], true);
    $isPostDetail = ($contentType ?? null) === 'post';
    $listingCollection = isset($listingItems) ? collect($listingItems->items()) : collect();
    $postFilters = $postFilters ?? ['q' => '', 'category' => ''];
    $postCategories = collect($postCategories ?? []);
    $latestPostItems = collect($latestPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
    $relatedPostItems = collect($relatedPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
    $footerColumns = $themeShellData['footer_columns'] ?? [];
    $companyFooter = $themeShellData['company_footer'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? $companyTitle }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-th0020::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .th-page { min-height: 100vh; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .th-topbar { background: #ffffff; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .th-topbar-inner { padding: 6px 0; }
            .th-inline { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
            .th-inline-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .th-inline-form { margin: 0; }
            .th-accent { color: var(--th-red); }
            .th-header { background: #f6f6f6; }
            .th-header-inner { padding: 12px 0; }
            .th-logo { display: flex; align-items: center; gap: 12px; min-width: 220px; }
            .th-logo img { width: 160px; height: 52px; object-fit: contain; }
            .th-search { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 52px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; background: #fff; max-width: 720px; }
            .th-search input, .th-search button { border: 0; height: 44px; font-size: 14px; }
            .th-search input { padding: 0 14px; background: transparent; }
            .th-search button { background: var(--th-red); color: #fff; font-weight: 700; cursor: pointer; }
            .th-cart { min-width: 120px; display: flex; justify-content: flex-end; font-weight: 700; color: #5f5f5f; }
            .th-main-nav { background: var(--th-red); color: #fff; }
            .th-main-nav-inner { position: relative; min-height: 42px; justify-content: flex-start; }
            .th-main-nav-categories-wrap { position: relative; }
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 210px; padding: 11px 14px; font-weight: 700; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; text-transform: uppercase; transition: color .18s ease; }
            .th-main-nav-menu a:hover { color: #fff2bf; }
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
            .th-content { padding: 20px 0 46px; }
            .th-preview-banner, .th-contact-status { margin-bottom: 16px; padding: 12px 16px; border-radius: 16px; }
            .th-preview-banner { background: #fff7e0; border: 1px solid #ffd591; color: #8a5a00; }
            .th-contact-status { background: #effcf3; border: 1px solid #9ae6b4; color: #166534; }
            .th-breadcrumb { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; color: #8a8a8a; font-size: 13px; }
            .th-breadcrumb span:last-child { color: var(--th-red); font-weight: 700; }
            .th-cms-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr); gap: 18px; padding: 28px; border: 1px solid #ffd8d8; background: linear-gradient(135deg, #fff6f1 0%, #ffffff 66%); box-shadow: var(--th-shadow); }
            .th-cms-hero-card, .th-cms-panel, .th-cms-card, .th-cms-article, .th-cms-sidebar-card { border-radius: 24px; background: #fff; }
            .th-cms-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: rgba(239, 43, 45, 0.09); color: var(--th-red); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .th-cms-title { margin: 16px 0 12px; font-size: clamp(30px, 5vw, 50px); line-height: 1.04; }
            .th-cms-summary { margin: 0; color: #6b6b6b; font-size: 16px; line-height: 1.8; }
            .th-cms-hero-actions { margin-top: 22px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .th-cms-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; font-weight: 800; border: 0; cursor: pointer; }
            .th-cms-button.primary { background: var(--th-red); color: #fff; }
            .th-cms-button.secondary { background: #fff; color: var(--th-red); border: 1px solid #ffd0d0; }
            .th-cms-hero-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .th-cms-stat { padding: 18px; border: 1px solid var(--th-line); border-radius: 22px; background: linear-gradient(180deg, #fff 0%, #fff7f7 100%); }
            .th-cms-stat strong { display: block; font-size: 28px; color: var(--th-red); }
            .th-cms-stat span { display: block; margin-top: 8px; color: #666; line-height: 1.5; }
            .th-cms-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; margin-top: 18px; }
            .th-cms-main-column { display: grid; gap: 18px; }
            .th-cms-panel, .th-cms-card, .th-cms-article, .th-cms-sidebar-card { border: 1px solid var(--th-line); box-shadow: var(--th-shadow); }
            .th-cms-panel { padding: 24px; }
            .th-cms-section-title { margin: 0 0 14px; font-size: 24px; }
            .th-cms-body { color: #444; line-height: 1.85; }
            .th-cms-body h2, .th-cms-body h3, .th-cms-body h4 { color: #202020; margin: 1.3em 0 0.65em; }
            .th-cms-body p, .th-cms-body ul, .th-cms-body ol { margin: 0 0 1em; }
            .th-cms-body ul, .th-cms-body ol { padding-left: 20px; }
            .th-cms-body blockquote { margin: 18px 0; padding: 16px 18px; border-left: 4px solid var(--th-red); background: #fff8f7; color: #5d4747; }
            .th-cms-image { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 22px; border: 1px solid var(--th-line); }
            .th-cms-feature { overflow: hidden; }
            .th-cms-feature-image { width: 100%; aspect-ratio: 16 / 8; object-fit: cover; }
            .th-cms-feature-body { padding: 24px; }
            .th-cms-card-media {
                width: 100%;
                aspect-ratio: 16 / 10;
                display: block;
                object-fit: cover;
                background: linear-gradient(135deg, #ffe0e0 0%, #fff4f4 100%);
                border-bottom: 1px solid var(--th-line);
            }
            .th-cms-card-media.is-placeholder {
                display: grid;
                place-items: center;
                color: var(--th-red);
                font-size: 14px;
                font-weight: 800;
                letter-spacing: .08em;
                text-transform: uppercase;
            }
            .th-cms-meta-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; color: #7a7a7a; font-size: 13px; }
            .th-cms-card-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
            .th-cms-card { overflow: hidden; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .th-cms-card:hover { transform: translateY(-4px); box-shadow: 0 20px 44px rgba(19, 21, 33, 0.12); border-color: #ffd2d2; }
            .th-cms-card a { cursor: pointer; }
            .th-cms-card-body { padding: 20px; }
            .th-cms-card-title { margin: 0 0 10px; font-size: 20px; line-height: 1.35; }
            .th-cms-card-title a { transition: color .18s ease; }
            .th-cms-card:hover .th-cms-card-title a { color: var(--th-red); }
            .th-cms-card-summary { margin: 0 0 18px; color: #666; line-height: 1.7; }
            .th-cms-link { color: var(--th-red); font-weight: 700; }
            .th-cms-listing-head { padding: 24px; }
            .th-cms-listing-head h1 { margin: 0 0 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.08; }
            .th-cms-listing-head p { margin: 0; color: #666; line-height: 1.75; }
            .th-news-toolbar { display: grid; gap: 12px; }
            .th-news-field { display: grid; gap: 8px; }
            .th-news-field span { font-size: 13px; font-weight: 700; color: #555; }
            .th-news-field input, .th-news-field select {
                width: 100%; min-height: 46px; padding: 0 14px; border: 1px solid #e5dede; border-radius: 14px; background: #fff; font: inherit;
            }
            .th-news-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .th-news-meta { margin-top: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; color: #666; }
            .th-news-meta strong { color: var(--th-red); }
            .th-news-empty { padding: 36px 24px; text-align: center; color: #666; }
            .th-cms-sidebar { display: grid; gap: 18px; }
            .th-cms-sidebar-card { padding: 20px; }
            .th-cms-sidebar-card h3 { margin: 0 0 14px; font-size: 20px; }
            .th-cms-contact-list, .th-cms-mini-list { display: grid; gap: 12px; }
            .th-cms-contact-item, .th-cms-mini-item { padding: 14px 16px; border-radius: 18px; background: #fafafa; border: 1px solid var(--th-line); }
            .th-cms-contact-item small, .th-cms-mini-item small { display: block; margin-bottom: 6px; color: #8b8b8b; text-transform: uppercase; letter-spacing: 0.08em; font-size: 11px; }
            .th-cms-contact-item strong, .th-cms-mini-item strong { display: block; font-size: 16px; line-height: 1.5; }
            .th-cms-contact-item span, .th-cms-mini-item span { display: block; margin-top: 6px; color: #666; line-height: 1.6; }
            .th-cms-highlight-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
            .th-cms-highlight { padding: 18px; border-radius: 20px; background: linear-gradient(180deg, #fff, #fff6f6); border: 1px solid #ffdede; }
            .th-cms-highlight strong { display: block; margin-bottom: 8px; font-size: 18px; color: var(--th-red); }
            .th-cms-highlight span { color: #666; line-height: 1.6; }
            .th-cms-pagination { margin-top: 22px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
            .th-cms-page-link { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--th-line); background: #fff; color: #555; font-weight: 700; }
            .th-cms-page-link.is-disabled { opacity: 0.45; pointer-events: none; }
            .th-related-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .th-contact-form { display: grid; gap: 14px; }
            .th-contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .th-contact-field { display: grid; gap: 8px; }
            .th-contact-field span { font-size: 14px; font-weight: 700; color: #444; }
            .th-contact-field input, .th-contact-field textarea {
                width: 100%; min-height: 46px; border: 1px solid #e5dede; border-radius: 16px; padding: 12px 14px; font: inherit; background: #fff;
            }
            .th-contact-field textarea { min-height: 150px; resize: vertical; }
            .th-contact-field input.has-error, .th-contact-field textarea.has-error { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.08); }
            .th-contact-error { min-height: 18px; color: #dc2626; font-size: 13px; line-height: 1.4; }
            .th-contact-simple { display: grid; gap: 24px; }
            .th-contact-simple-head { padding: 28px; border: 1px solid #ffe0d6; border-radius: 28px; background: linear-gradient(180deg, #fff 0%, #fff9f6 100%); box-shadow: var(--th-shadow); text-align: center; }
            .th-contact-simple-head h1 { margin: 0 0 12px; font-size: clamp(34px, 5vw, 54px); line-height: 1.04; }
            .th-contact-simple-head p { margin: 0 auto; max-width: 860px; color: #666; font-size: 17px; line-height: 1.8; }
            .th-contact-info-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .th-contact-info-card { display: grid; grid-template-columns: 54px minmax(0, 1fr); gap: 16px; align-items: start; padding: 22px; border-radius: 24px; border: 1px solid var(--th-line); background: #fff; box-shadow: var(--th-shadow); }
            .th-contact-info-icon { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 18px; background: #fff2ef; color: var(--th-red); font-size: 28px; }
            .th-contact-info-card small { display: block; margin-bottom: 6px; color: #8b8b8b; font-size: 11px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .th-contact-info-card strong { display: block; margin-bottom: 6px; font-size: 22px; line-height: 1.25; }
            .th-contact-info-card span { display: block; color: #666; line-height: 1.7; }
            .th-contact-form-shell { width: 100%; max-width: none; margin: 0; padding: 30px; border-radius: 28px; border: 1px solid var(--th-line); background: #fff; box-shadow: var(--th-shadow); }
            .th-contact-form-shell h2 { margin: 0 0 10px; text-align: center; font-size: clamp(28px, 4vw, 42px); }
            .th-contact-form-shell > p { margin: 0 0 24px; text-align: center; color: #666; line-height: 1.7; }
            .th-contact-form-shell .th-contact-form { gap: 16px; }
            .th-contact-submit-row { display: flex; justify-content: center; }
            .th-about-simple { width: 100%; max-width: none; margin: 0; padding: 32px; border-radius: 28px; border: 1px solid #ffe0d6; background: linear-gradient(180deg, #fff 0%, #fff9f6 100%); box-shadow: var(--th-shadow); }
            .th-about-simple .th-cms-title { margin-bottom: 10px; }
            .th-about-simple .th-cms-body { margin-top: 20px; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: 1.2fr repeat(3, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }

            @media (max-width: 1100px) {
                .th-cms-hero, .th-cms-grid, .th-related-grid { grid-template-columns: 1fr; }
                .th-cms-highlight-grid { grid-template-columns: 1fr; }
                .th-contact-info-strip { grid-template-columns: 1fr; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { text-align: center; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-categories { min-width: 0; width: 100%; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-category-panel { width: min(320px, calc(100vw - 24px)); }
                .th-cms-hero, .th-cms-panel, .th-cms-feature-body, .th-cms-sidebar-card { padding: 18px; }
                .th-cms-hero-meta, .th-cms-card-grid, .th-footer-grid, .th-contact-grid, .th-news-toolbar { grid-template-columns: 1fr; }
                .th-cms-card-media { aspect-ratio: 4 / 3; }
            }
            @include('theme-th0020::partials.interior-shell-styles')
        </style>
    </head>
    <body>
        <div class="th-page th-interior-page">
            @include('theme-th0020::partials.interior-header')
            <div class="th-legacy-header" hidden>
            <div class="th-topbar">
                <div class="th-container th-topbar-inner">
                    <div class="th-inline">
                        <span>?? {{ $contactLocation }}</span>
                        <button type="button" class="th-inline-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}</button>
                    </div>
                    <div class="th-inline">
                        <span>?? @themeT('common.hotline_label', 'Hotline'): <span class="th-accent">{{ $contactHotline }}</span></span>
                        <span>? @themeT('common.email_label', 'Email'): {{ $contactEmail }}</span>
                        @if (!empty($customerAuth['is_authenticated']))
                            <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">@themeT('common.account', 'Tài kho?n')</a>
                            <form class="th-inline-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                                @csrf
                                <button type="submit" class="th-inline-action">@themeT('common.logout', 'Ðang xu?t')</button>
                            </form>
                        @else
                            <button type="button" class="th-inline-action" data-open-auth-modal="register">@themeT('common.register', 'Ðang ký')</button>
                            <button type="button" class="th-inline-action" data-open-auth-modal="login">@themeT('common.login', 'Ðang nh?p')</button>
                        @endif
                    </div>
                </div>
            </div>

            <header class="th-header">
                <div class="th-container th-header-inner">
                    <a class="th-logo" href="{{ route('site.home') }}">
                        <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ $companyTitle ?: '' }}">
                    </a>
                    <form class="th-search" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('common.search_placeholder', 'Tìm ki?m s?n ph?m / khuy?n mãi')" aria-label="@themeT('common.search_aria', 'Tìm ki?m s?n ph?m')" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                        <button type="submit">@themeT('common.search_button', 'Tìm')</button>
                    </form>
                    <a class="th-cart" href="{{ route('site.cart.index') }}">&#128722; {{ $cartSummary['count'] ?? 0 }} @themeT('common.cart_label', 'GI? HÀNG')</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories-wrap">
                        <div class="th-main-nav-categories">@themeT('common.categories', 'DANH M?C')</div>
                        <div class="th-category-panel">
                            @foreach ($productMenu as $item)
                                <div class="th-sidebar-entry">
                                    <a href="{{ $item['url'] ?? route('site.catalog.search') }}" target="{{ $item['target'] ?? '_self' }}" class="th-sidebar-item {{ !empty($item['highlight']) ? 'is-accent' : '' }}">
                                        <span><span class="th-sidebar-icon">{{ $item['icon'] ?? '•' }}</span> {{ $item['label'] ?? __('common.category') }}</span>
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
                                                        <h4>{{ $item['label'] ?? __('common.category') }}</h4>
                                                        <ul>
                                                            @foreach ($chunk as $child)
                                                                <li><a href="{{ $child['url'] ?? ($item['url'] ?? route('site.catalog.search')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? $t('common.child_group', 'Nhóm con') }}</a></li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="th-sidebar-mega-promo">
                                                @foreach ($sidePromos as $promo)
                                                    <a href="{{ $promo['link_url'] ?? route('site.catalog.search') }}">
                                                        <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                                        <span>{{ $promo['title'] }}{{ filled($promo['subtitle'] ?? null) ? ' ? '.$promo['subtitle'] : '' }}</span>
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
                        @foreach ($topMenu as $menuItem)
                            <a href="{{ $menuItem['url'] ?? route('site.home') }}" target="{{ $menuItem['target'] ?? '_self' }}">{{ $menuItem['label'] ?? $t('common.menu', 'Menu') }}</a>
                        @endforeach
                    </div>
                </div>
            </nav>
            </div>

            <main class="th-content">
                <div class="th-container">
                    @if (!empty($isPreview))
                        <div class="th-preview-banner">@themeT('cms.preview_banner', 'Ðây là ch? d? preview unpublished ch? dành cho admin.')</div>
                    @endif
                    @if (session('contact_status'))
                        <div class="th-contact-status">{{ session('contact_status') }}</div>
                    @endif

                    <div class="th-breadcrumb">
                        <a href="{{ route('site.home') }}">@themeT('common.home', 'Trang ch?')</a>
                        <span>/</span>
                        <span>{{ $pageTitle ?? ($entry->title ?? __('cms.default_title')) }}</span>
                    </div>

                    @if (($contentType ?? null) === 'posts')
                        <div class="th-cms-grid" id="news-grid">
                            <div class="th-cms-main-column">
                                <section class="th-cms-panel th-cms-listing-head">
                                    <h1>{{ $pageTitle }}</h1>
                                    <p>{{ $pageDescription }}</p>
                                    <div class="th-news-meta">
                                        <span>{{ str_replace(':count', (string) (method_exists($listingItems, 'total') ? $listingItems->total() : $listingCollection->count()), __('blog.found_posts')) }}</span>
                                        @if (filled($postFilters['q'] ?? '') || filled($postFilters['category'] ?? ''))
                                            <span>@themeT('blog.filter_applied', 'Ðang áp d?ng b? l?c cho danh sách tin t?c.')</span>
                                        @endif
                                    </div>
                                </section>

                                @if ($listingCollection->isNotEmpty())
                                    <section class="th-cms-card-grid">
                                        @foreach ($listingCollection as $post)
                                            <article class="th-cms-card">
                                                <a href="{{ route('site.blog.show', $post->slug) }}" aria-label="{{ $post->title }}">
                                                    <img
                                                        class="th-cms-card-media{{ empty($post->featuredMedia?->file_url ?? null) ? ' is-placeholder' : '' }}"
                                                        src="{{ $post->featuredMedia?->file_url ?? ('https://picsum.photos/seed/cms-post-'.($post->id ?? 'default').'/960/720') }}"
                                                        alt="{{ $post->title }}">
                                                </a>
                                                <div class="th-cms-card-body">
                                                    <div class="th-cms-meta-row">
                                                        <span>{{ optional($post->publish_at)->format('d/m/Y') ?? $t('cms.updating', 'Ðang c?p nh?t') }}</span>
                                                        @if (!empty($post->category?->name))
                                                            <span>{{ $post->category->name }}</span>
                                                        @endif
                                                    </div>
                                                    <h3 class="th-cms-card-title"><a href="{{ route('site.blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
                                                    <p class="th-cms-card-summary">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 150) }}</p>
                                                </div>
                                            </article>
                                        @endforeach
                                    </section>
                                @else
                                    <section class="th-cms-panel th-news-empty">
                                        <h3>@themeT('blog.empty_title', 'Không tìm th?y bài vi?t phù h?p')</h3>
                                        <p>@themeT('blog.empty_description', 'Hãy th? t? khóa khác ho?c b? b?t b? l?c d? xem thêm n?i dung.')</p>
                                    </section>
                                @endif

                                @if (method_exists($listingItems, 'previousPageUrl') || method_exists($listingItems, 'nextPageUrl'))
                                    <div class="th-cms-pagination">
                                        <a href="{{ $listingItems->previousPageUrl() ?: '#' }}" class="th-cms-page-link {{ $listingItems->previousPageUrl() ? '' : 'is-disabled' }}">@themeT('blog.newer_posts', 'Bài m?i hon')</a>
                                        <span>{{ str_replace([':current', ':last'], [(string) $listingItems->currentPage(), (string) $listingItems->lastPage()], $t('search.page_of', 'Trang :current / :last')) }}</span>
                                        <a href="{{ $listingItems->nextPageUrl() ?: '#' }}" class="th-cms-page-link {{ $listingItems->nextPageUrl() ? '' : 'is-disabled' }}">@themeT('blog.older_posts', 'Bài cu hon')</a>
                                    </div>
                                @endif
                            </div>

                            <aside class="th-cms-sidebar">
                                <section class="th-cms-sidebar-card">
                                    <h3>@themeT('blog.search_title', 'Tìm ki?m tin t?c')</h3>
                                    <form method="GET" action="{{ route('site.blog.index') }}" class="th-news-toolbar">
                                        <label class="th-news-field">
                                            <span>@themeT('blog.keyword', 'T? khóa')</span>
                                            <input type="search" name="q" value="{{ $postFilters['q'] ?? '' }}" placeholder="@themeT('blog.keyword_placeholder', 'Nh?p tiêu d?, mô t? ho?c t? khóa n?i dung')">
                                        </label>
                                        <label class="th-news-field">
                                            <span>@themeT('blog.category', 'Chuyên m?c')</span>
                                            <select name="category">
                                                <option value="">@themeT('blog.all_categories', 'T?t c? chuyên m?c')</option>
                                                @foreach ($postCategories as $category)
                                                    <option value="{{ $category->slug }}" @selected(($postFilters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <div class="th-news-actions">
                                            <button type="submit" class="th-cms-button primary">@themeT('blog.filter_button', 'L?c tin')</button>
                                            @if (filled($postFilters['q'] ?? '') || filled($postFilters['category'] ?? ''))
                                                <a href="{{ route('site.blog.index') }}" class="th-cms-button secondary">@themeT('blog.clear_filters', 'Xóa l?c')</a>
                                            @else
                                                <button type="button" class="th-cms-button secondary" data-open-newsletter-modal>@themeT('blog.newsletter_button', 'Nh?n b?n tin')</button>
                                            @endif
                                        </div>
                                    </form>
                                </section>
                                <section class="th-cms-sidebar-card">
                                    <h3>@themeT('blog.newsletter_title', 'Nh?n b?n tin uu dãi')</h3>
                                    <p class="th-cms-summary">@themeT('blog.newsletter_summary', 'Ðang ký email d? nh?n bài vi?t m?i, l?ch campaign và g?i ý s?n ph?m n?i b?t.')</p>
                                    <button type="button" class="th-cms-button primary" data-open-newsletter-modal>@themeT('blog.newsletter_cta', 'Ðang ký ngay')</button>
                                </section>
                                <section class="th-cms-sidebar-card">
                                    <h3>@themeT('blog.quick_connect', 'K?t n?i nhanh')</h3>
                                    <div class="th-cms-contact-list">
                                        <div class="th-cms-contact-item">
                                            <small>@themeT('blog.quick_hotline', 'Hotline')</small>
                                            <strong>{{ $contactHotline }}</strong>
                                        </div>
                                        <div class="th-cms-contact-item">
                                            <small>@themeT('blog.quick_email', 'Email')</small>
                                            <strong>{{ $contactEmail }}</strong>
                                        </div>
                                        <div class="th-cms-contact-item">
                                            <small>@themeT('blog.quick_location', 'Khu v?c')</small>
                                            <strong>{{ $contactLocation }}</strong>
                                        </div>
                                    </div>
                                </section>
                            </aside>
                        </div>
                    @else
                        @if ($isContactPage)
                            <section class="th-contact-simple">
                                <div class="th-contact-simple-head">
                                    <span class="th-cms-kicker">{{ $t('cms.contact.kicker', 'K?t n?i v?i chúng tôi') }}</span>
                                    <h1>{{ $entry->title }}</h1>
                                    <p>{{ $entry->excerpt ?: $t('cms.contact.intro', 'B?n có th? g?i ngay th?c m?c ho?c nhu c?u h?p tác qua form bên du?i. Chúng tôi uu tiên cách trình bày ng?n g?n, rõ d?u m?i liên h? và d? thao tác trên c? desktop l?n mobile.') }}</p>
                                </div>

                                <div class="th-contact-info-strip">
                                    <article class="th-contact-info-card">
                                        <div class="th-contact-info-icon">¦</div>
                                        <div>
                                            <small>{{ $t('cms.contact.address_label', 'Ð?a ch?') }}</small>
                                            <strong>{{ $contactLocation }}</strong>
                                            <span>{{ $t('cms.contact.address_copy', 'Ð?a di?m ti?p nh?n trao d?i, làm vi?c v?i kinh doanh và h? tr? khách hàng.') }}</span>
                                        </div>
                                    </article>
                                    <article class="th-contact-info-card">
                                        <div class="th-contact-info-icon">?</div>
                                        <div>
                                            <small>Hotline</small>
                                            <strong>{{ $contactHotline }}</strong>
                                            <span>{{ $t('cms.contact.hotline_copy', 'Liên h? nhanh d? du?c h? tr? tu v?n, xác nh?n nhu c?u và x? lý yêu c?u g?p.') }}</span>
                                        </div>
                                    </article>
                                    <article class="th-contact-info-card">
                                        <div class="th-contact-info-icon">?</div>
                                        <div>
                                            <small>Email</small>
                                            <strong>{{ $contactEmail }}</strong>
                                            <span>{{ $t('cms.contact.email_copy', 'Kênh phù h?p d? g?i brief, yêu c?u báo giá và trao d?i chi ti?t hon.') }}</span>
                                        </div>
                                    </article>
                                </div>

                                <section class="th-contact-form-shell">
                                    <h2>{{ $t('cms.contact.form_title', 'Liên h? ngay') }}</h2>
                                    <p>{{ $t('cms.contact.form_summary', 'G?i th?c m?c ho?c thông tin liên h? c?a b?n, chúng tôi s? ph?n h?i trong th?i gian s?m nh?t.') }}</p>
                                    <form method="POST" action="{{ route('site.contact.submit') }}" class="th-contact-form" novalidate>
                                        @csrf
                                        <div class="th-contact-grid">
                                            <label class="th-contact-field">
                                                <span>{{ $t('cms.contact.name_label', 'H? và tên') }}</span>
                                                <input type="text" name="name" value="{{ old('name') }}" class="{{ $errors->has('name') ? 'has-error' : '' }}" placeholder="{{ $t('cms.contact.name_placeholder', 'Nh?p tên c?a b?n') }}" required>
                                                <small class="th-contact-error">{{ $errors->first('name') }}</small>
                                            </label>
                                            <label class="th-contact-field">
                                                <span>Email</span>
                                                <input type="email" name="email" value="{{ old('email') }}" class="{{ $errors->has('email') ? 'has-error' : '' }}" placeholder="{{ $t('cms.contact.email_placeholder', 'Nh?p email c?a b?n') }}" required>
                                                <small class="th-contact-error">{{ $errors->first('email') }}</small>
                                            </label>
                                        </div>
                                        <div class="th-contact-grid">
                                            <label class="th-contact-field">
                                                <span>{{ $t('cms.contact.phone_label', 'S? di?n tho?i') }}</span>
                                                <input type="text" name="phone" value="{{ old('phone') }}" class="{{ $errors->has('phone') ? 'has-error' : '' }}" placeholder="{{ $t('cms.contact.phone_placeholder', 'Ði?n tho?i') }}">
                                                <small class="th-contact-error">{{ $errors->first('phone') }}</small>
                                            </label>
                                            <label class="th-contact-field">
                                                <span>{{ $t('cms.contact.subject_label', 'Ch? d?') }}</span>
                                                <input type="text" name="subject" value="{{ old('subject') }}" class="{{ $errors->has('subject') ? 'has-error' : '' }}" placeholder="{{ $t('cms.contact.subject_placeholder', 'Tiêu d?') }}">
                                                <small class="th-contact-error">{{ $errors->first('subject') }}</small>
                                            </label>
                                        </div>
                                        <label class="th-contact-field">
                                            <span>{{ $t('cms.contact.message_label', 'N?i dung') }}</span>
                                            <textarea name="message" class="{{ $errors->has('message') ? 'has-error' : '' }}" placeholder="{{ $t('cms.contact.message_placeholder', 'Nh?p n?i dung c?a b?n') }}" required>{{ old('message') }}</textarea>
                                            <small class="th-contact-error">{{ $errors->first('message') }}</small>
                                        </label>
                                        <div class="th-contact-submit-row">
                                            <button type="submit" class="th-cms-button primary">{{ $t('cms.contact.submit', 'G?i yêu c?u liên h?') }}</button>
                                        </div>
                                    </form>
                                </section>
                            </section>
                        @elseif ($isAboutPage)
                            <section class="th-about-simple">
                                <span class="th-cms-kicker">{{ $t('cms.about.kicker', 'H? so thuong hi?u') }}</span>
                                <h1 class="th-cms-title">{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="th-cms-summary">{{ $entry->excerpt }}</p>
                                @endif
                                <article class="th-cms-body">{!! $entry->body ?: '<p>'.$t('cms.content_updating', 'N?i dung dang du?c c?p nh?t.').'</p>' !!}</article>
                            </section>
                        @elseif ($isPostDetail)
                            <section class="th-cms-hero">
                                <div class="th-cms-hero-card">
                                    <span class="th-cms-kicker">{{ $t('cms.post.kicker', 'Bài vi?t chi ti?t') }}</span>
                                    <h1 class="th-cms-title">{{ $entry->title }}</h1>
                                    @if (!empty($entry->excerpt))
                                        <p class="th-cms-summary">{{ $entry->excerpt }}</p>
                                    @endif
                                    <div class="th-cms-hero-actions">
                                        <a href="{{ route('site.blog.index') }}" class="th-cms-button primary">{{ $t('cms.post.back_to_news', 'V? trang tin t?c') }}</a>
                                        <button type="button" class="th-cms-button secondary" data-open-newsletter-modal>{{ $t('blog.newsletter_button', 'Nh?n b?n tin') }}</button>
                                    </div>
                                </div>
                            </section>
                        @else
                            <section class="th-about-simple">
                                <h1 class="th-cms-title">{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="th-cms-summary">{{ $entry->excerpt }}</p>
                                @endif
                                <article class="th-cms-body">{!! $entry->body ?: '<p>'.$t('cms.content_updating', 'N?i dung dang du?c c?p nh?t.').'</p>' !!}</article>
                            </section>
                        @endif

                        @if ($isPostDetail)
                        <div class="th-cms-grid">
                            <div class="th-cms-main-column">
                                @if (!empty($entry->featuredMedia?->file_url ?? null))
                                    <section class="th-cms-panel">
                                        <img class="th-cms-image" src="{{ $entry->featuredMedia->file_url }}" alt="{{ $entry->title }}">
                                    </section>
                                @endif

                                @if ($isAboutPage)
                                    <section class="th-cms-panel">
                                        <h2 class="th-cms-section-title">{{ $t('cms.about.operations_title', 'H? so v?n hành') }}</h2>
                                        <div class="th-cms-highlight-grid">
                                            <div class="th-cms-highlight">
                                                <strong>{{ $t('cms.about.highlight_sync_title', 'Ð?ng b? CMS và storefront') }}</strong>
                                                <span>{{ $t('cms.about.highlight_sync_body', 'N?i dung gi?i thi?u, tin t?c và d? li?u thuong hi?u du?c render tr?c ti?p trên giao di?n TH0020.') }}</span>
                                            </div>
                                            <div class="th-cms-highlight">
                                                <strong>{{ $t('cms.about.highlight_contact_title', 'Thông tin liên h? t?p trung') }}</strong>
                                                <span>{{ $t('cms.about.highlight_contact_body', 'Hotline, email và d?a di?m l?y t? c?u hình website d? tránh hard-code theo t?ng trang.') }}</span>
                                            </div>
                                            <div class="th-cms-highlight">
                                                <strong>{{ $t('cms.about.highlight_flow_title', 'Tr?i nghi?m mua hàng li?n m?ch') }}</strong>
                                                <span>{{ $t('cms.about.highlight_flow_body', 'Khách hàng có th? di t? n?i dung gi?i thi?u sang tin t?c, s?n ph?m và checkout trong cùng m?t h? th?ng.') }}</span>
                                            </div>
                                        </div>
                                    </section>
                                @endif

                                <article class="th-cms-article th-cms-panel">
                                    <div class="th-cms-meta-row">
                                        <span>{{ optional($entry->publish_at)->format('d/m/Y H:i') ?? $t('cms.updating', 'Ðang c?p nh?t') }}</span>
                                        @if (!empty($entry->category?->name))
                                            <span>{{ $entry->category->name }}</span>
                                        @endif
                                    </div>
                                    <div class="th-cms-body">{!! $entry->body ?: '<p>'.$t('cms.content_updating', 'N?i dung dang du?c c?p nh?t.').'</p>' !!}</div>
                                </article>

                                @if ($isPostDetail && $relatedPostItems->isNotEmpty())
                                    <section class="th-cms-panel">
                                        <h2 class="th-cms-section-title">{{ $t('cms.post.related_title', 'Bài liên quan') }}</h2>
                                        <div class="th-related-grid">
                                            @foreach ($relatedPostItems as $post)
                                                <article class="th-cms-card">
                                                    <a href="{{ route('site.blog.show', $post->slug) }}" aria-label="{{ $post->title }}">
                                                        <img class="th-cms-feature-image" src="{{ $post->featuredMedia?->file_url ?? ('https://picsum.photos/seed/related-post-'.($post->id ?? 'default').'/960/720') }}" alt="{{ $post->title }}">
                                                    </a>
                                                    <div class="th-cms-card-body">
                                                        <h3 class="th-cms-card-title"><a href="{{ route('site.blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            </div>

                            <aside class="th-cms-sidebar">
                                <section class="th-cms-sidebar-card">
                                    <h3>{{ $t('cms.sidebar.quick_info_title', 'Thông tin nhanh') }}</h3>
                                    <div class="th-cms-mini-list">
                                        <div class="th-cms-mini-item">
                                            <small>{{ $t('cms.sidebar.brand_label', 'Thuong hi?u') }}</small>
                                            <strong>{{ data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', '')) }}</strong>
                                        </div>
                                        <div class="th-cms-mini-item">
                                            <small>Hotline</small>
                                            <strong>{{ $contactHotline }}</strong>
                                        </div>
                                        <div class="th-cms-mini-item">
                                            <small>Email</small>
                                            <strong>{{ $contactEmail }}</strong>
                                        </div>
                                    </div>
                                </section>
                                @if ($relatedPostItems->isNotEmpty())
                                    <section class="th-cms-sidebar-card">
                                        <h3>{{ $t('cms.sidebar.read_more_title', 'Ð?c ti?p') }}</h3>
                                        <div class="th-cms-mini-list">
                                            @foreach ($relatedPostItems as $post)
                                                <a class="th-cms-mini-item" href="{{ route('site.blog.show', $post->slug) }}">
                                                    <small>{{ optional($post->publish_at)->format('d/m/Y') ?? $t('cms.post.article_label', 'Bài vi?t') }}</small>
                                                    <strong>{{ $post->title }}</strong>
                                                    <span>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 90) }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </section>
                                @elseif ($latestPostItems->isNotEmpty())
                                    <section class="th-cms-sidebar-card">
                                        <h3>{{ $t('cms.sidebar.latest_news_title', 'Tin m?i') }}</h3>
                                        <div class="th-cms-mini-list">
                                            @foreach ($latestPostItems as $post)
                                                <a class="th-cms-mini-item" href="{{ route('site.blog.show', $post->slug) }}">
                                                    <small>{{ optional($post->publish_at)->format('d/m/Y') ?? $t('cms.post.article_label', 'Bài vi?t') }}</small>
                                                    <strong>{{ $post->title }}</strong>
                                                    <span>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 90) }}</span>
                                                @endforeach
                                            </div>
                                        </section>
                                @endif
                            </aside>
                        </div>
                        @endif
                    @endif
                </div>
            </main>

            @include('theme-th0020::partials.footer', ['footerContainerClass' => 'th-container', 'footerCompanyFirst' => true])
        </div>

        @include('theme-th0020::partials.product-search-autocomplete')
        @include('theme-th0020::partials.engagement-modals')
    </body>
</html>
