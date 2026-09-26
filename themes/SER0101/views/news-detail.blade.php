@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '');
    $contactEmail = data_get($branding, 'support_email', '');
    $contactLocation = data_get($branding, 'support_location', '');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $listingCollection = isset($listingItems) ? collect($listingItems->items()) : collect();
    $latestPostItems = collect($latestPosts ?? [])->take(3)->values();
    $relatedPostItems = collect($relatedPosts ?? [])->take(3)->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? data_get($branding, 'company_name', 'SER0101') }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            :root {
                --navy: #0f172f;
                --night: #08111f;
                --teal: #0f766e;
                --orange: #b45309;
                --amber: #f0b429;
                --line: #d6e2de;
                --muted: #5d7288;
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.1), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
                color: #243b53;
            }

            a { text-decoration: none; color: inherit; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1160px, calc(100% - 24px)); margin: 0 auto; }
            .hero {
                margin: 22px 0 18px;
                padding: 20px;
                border-radius: 34px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: 0 24px 56px rgba(10, 30, 47, 0.16);
            }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) 300px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 8px 10px 4px; }
            .badge { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
            .hero h1 { margin: 16px 0 10px; font-size: clamp(34px, 5vw, 56px); line-height: 1.02; }
            .hero p { margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-dossier {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-dossier strong { display: block; font-size: 22px; line-height: 1.15; }
            .hero-dossier p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-dossier-list { display: grid; gap: 10px; }
            .hero-dossier-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-dossier-item b { display: block; font-size: 18px; }
            .hero-dossier-item small { color: #cbd5e1; line-height: 1.5; }
            .layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; margin-bottom: 32px; align-items: start; }
            .grid { display: grid; gap: 18px; }
            .panel, .side-card, .post-card {
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(214, 226, 222, 0.92);
                border-radius: 26px;
                box-shadow: 0 16px 36px rgba(16, 42, 67, 0.08);
            }
            .panel { padding: 24px; }
            .panel h2, .side-card h3 { margin: 0 0 14px; color: var(--navy); }
            .body-copy { color: #334e68; line-height: 1.9; }
            .body-copy h2, .body-copy h3, .body-copy h4 { color: var(--navy); }
            .body-copy blockquote { margin: 18px 0; padding: 16px 18px; border-left: 4px solid var(--orange); background: #fff8f1; }
            .toolbar { display: grid; gap: 12px; }
            .toolbar input, .toolbar select { min-height: 46px; border: 1px solid var(--line); border-radius: 14px; padding: 0 14px; font: inherit; }
            .toolbar-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .toolbar-buttons button, .toolbar-buttons a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: 999px; font-weight: 800; }
            .toolbar-buttons button { border: 0; background: linear-gradient(135deg, var(--teal), var(--p-deep)); color: #fff; }
            .toolbar-buttons a { border: 1px solid var(--line); background: #fff; color: var(--navy); }
            .post-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .post-card { overflow: hidden; }
            .post-card img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; background: #edf2f7; }
            .post-card-body { padding: 18px; }
            .post-card h4 { margin: 0 0 10px; color: var(--navy); font-size: 22px; letter-spacing: -0.02em; }
            .post-card p { margin: 0; color: var(--muted); line-height: 1.7; }
            .meta { margin-bottom: 10px; color: var(--orange); font-size: 13px; font-weight: 700; }
            .side-stack { display: grid; gap: 18px; align-content: start; }
            .side-card { padding: 20px; }
            .side-card strong { display: block; color: var(--navy); margin-bottom: 6px; }
            .side-card p, .side-card a, .side-card span { color: var(--muted); line-height: 1.7; }
            .side-post-list { display: grid; gap: 12px; }
            .side-post-item { display: grid; gap: 4px; padding-top: 12px; border-top: 1px solid rgba(217, 226, 236, 0.9); }
            .side-post-item:first-child { padding-top: 0; border-top: 0; }
            .side-post-item strong { margin-bottom: 0; font-size: 15px; line-height: 1.45; }
            .side-post-item span { font-size: 13px; line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .pagination { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
            .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--line); background: #fff; color: #486581; font-weight: 700; }
            .footer { background: var(--navy); color: #d9e2ec; }
            .footer-inner { padding: 28px 0 34px; align-items: flex-start; }
            .footer-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 24px; width: 100%; }
            .footer-grid h4 { margin: 0 0 12px; color: #fff; }
            .footer-grid p, .footer-grid a { color: #bcccdc; line-height: 1.8; }

            @media (max-width: 900px) {
                .hero-grid, .layout, .post-grid, .footer-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])
        @include('themes.common.news-detail')
        @include('theme-ser0101::partials.shell-footer')
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
