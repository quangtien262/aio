@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $sidePromos = $shell['side_banners'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('TH0020', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@TH0020.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà N?i');
    $companyTitle = data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', ''));
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $gallery = $productGallery ?? [];
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $footerColumns = $shell['footer_columns'] ?? [];
    $companyFooter = $shell['company_footer'] ?? [];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/TH0020-product-fallback/960/720');
    $discount = (int) ($product['discount'] ?? 0);
    $soldCount = (int) ($productModel->sold_count ?? 0);
    $deadline = $productModel->deal_end_at?->toIso8601String();
    $qrPayload = rawurlencode(($productModel->sku ?? 'AIO').'-'.($productModel->slug ?? $productModel->id));
    $formatCurrency = fn ($value) => $value === null ? 'Liên h?' : number_format((float) $value, 0, ',', '.').'d';
    $maxPurchaseQuantity = $productModel->stock !== null && (int) $productModel->stock > 0
        ? max(1, min(5, (int) $productModel->stock))
        : 5;
    $orderGuideSteps = [
        [
            'step' => '01',
            'title' => __('product.guide_step_1_title'),
            'body' => __('product.guide_step_1_body'),
        ],
        [
            'step' => '02',
            'title' => __('product.guide_step_2_title'),
            'body' => __('product.guide_step_2_body'),
        ],
        [
            'step' => '03',
            'title' => __('product.guide_step_3_title'),
            'body' => __('product.guide_step_3_body'),
        ],
        [
            'step' => '04',
            'title' => __('product.guide_step_4_title'),
            'body' => __('product.guide_step_4_body'),
        ],
    ];
    $orderGuideNotes = [
        __('product.guide_note_1'),
        __('product.guide_note_2'),
        __('product.guide_note_3'),
    ];

    if ($highlights === [] && filled($productModel->short_description)) {
        $highlights = [trim((string) $productModel->short_description)];
    }

    if ($detailParagraphsList === []) {
        $detailParagraphsList = [
            $productModel->short_description ?: __('product.detail_default_1'),
            __('product.detail_default_2'),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $product['title'] }}{{ $companyTitle ? ' | '.$companyTitle : '' }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-th0020::partials.palette-tokens', ['branding' => $branding])
            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--th-bg); color: var(--th-ink); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            button { font: inherit; }
            .wrap { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .utility { background: #f8f8f8; border-bottom: 1px solid var(--th-line); font-size: 13px; color: var(--th-muted); }
            .utility-inner { display: flex; justify-content: space-between; gap: 14px; padding: 8px 0; flex-wrap: wrap; }
            .utility-actions, .utility-group { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
            .utility-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .utility-form { margin: 0; }
            .header { background: #fff; }
            .header-main { display: grid; grid-template-columns: 220px 1fr auto; align-items: center; gap: 18px; padding: 16px 0; }
            .brand img { width: 184px; height: 52px; object-fit: contain; }
            .searchbar { display: grid; grid-template-columns: minmax(0, 1fr) 56px; align-items: stretch; height: 42px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; }
            .searchbar input { border: 0; background: #fff; padding: 0 14px; font-size: 14px; }
            .searchbar input { flex: 1; min-width: 0; }
            .searchbar button { width: 56px; border: 0; background: var(--th-red); color: #fff; font-size: 18px; cursor: pointer; }
            .cart-link { font-size: 14px; font-weight: 700; color: #444; }
            .flash-banner { margin: 0 0 18px; padding: 14px 16px; border: 1px solid #c9e6b0; background: #f5ffe9; color: #3f6a18; font-size: 14px; }
            .nav { background: var(--th-red); color: #fff; }
            .nav-inner { position: relative; display: flex; align-items: center; justify-content: flex-start; gap: 28px; min-height: 42px; font-size: 14px; font-weight: 700; }
            .nav-category-wrap { position: relative; }
            .nav-category { background: rgba(0, 0, 0, .16); padding: 12px 18px; min-width: 210px; }
            .nav-category-panel { position: absolute; top: 100%; left: 0; width: 220px; background: #fff; border: 1px solid var(--th-line); z-index: 30; display: none; }
            .nav-category-wrap:hover .nav-category-panel { display: block; }
            .nav-links { display: flex; justify-content: flex-start; gap: 28px; flex-wrap: wrap; }
            .nav-links a { text-align: left; text-transform: uppercase; transition: color .18s ease; }
            .nav-links a:hover { color: #fff2bf; }
            .nav-category-panel .th-sidebar-entry { position: static; }
            .nav-category-panel .th-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 14px; border-bottom: 1px solid var(--th-line); font-size: 14px; color: #4f4f4f; background: #fff; transition: background .16s ease, color .16s ease; }
            .nav-category-panel .th-sidebar-entry:last-child .th-sidebar-item { border-bottom: 0; }
            .nav-category-panel .th-sidebar-entry:hover .th-sidebar-item { color: var(--th-red); background: #fff7f7; }
            .nav-category-panel .th-sidebar-item.is-accent { color: var(--th-red); font-weight: 700; }
            .nav-category-panel .th-sidebar-icon { width: 20px; color: #979797; }
            .nav-category-panel .th-sidebar-mega { position: absolute; top: -1px; left: 100%; width: calc(100vw - max((100vw - 1200px) / 2, 12px) * 2 - 220px); max-width: 948px; min-height: 302px; display: grid; grid-template-columns: minmax(0, 1fr) 220px; background: #fff; border: 1px solid var(--th-line); box-shadow: 0 24px 48px rgba(21, 24, 34, 0.12); z-index: 8; opacity: 0; visibility: hidden; pointer-events: none; transform: translate3d(12px, 0, 0); transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .nav-category-panel .th-sidebar-mega::before { content: ''; position: absolute; top: 0; left: -20px; width: 20px; height: 100%; }
            .nav-category-panel .th-sidebar-entry:hover .th-sidebar-mega { opacity: 1; visibility: visible; pointer-events: auto; transform: translate3d(0, 0, 0); }
            .nav-category-panel .th-sidebar-mega-content { display: grid; grid-template-columns: 170px 1fr 1.15fr; gap: 34px; padding: 22px 26px 22px 24px; align-content: start; }
            .nav-category-panel .th-sidebar-mega-content.has-four .th-sidebar-mega-column:nth-child(4) { grid-column: 1 / 2; align-self: start; }
            .nav-category-panel .th-sidebar-mega.mega-hot { max-width: 920px; grid-template-columns: minmax(0, 1fr) 218px; }
            .nav-category-panel .th-sidebar-mega.mega-hot .th-sidebar-mega-content { grid-template-columns: 180px 1fr 1fr; gap: 30px; }
            .nav-category-panel .th-sidebar-mega.mega-food { max-width: 930px; grid-template-columns: minmax(0, 1fr) 220px; }
            .nav-category-panel .th-sidebar-mega.mega-food .th-sidebar-mega-content { grid-template-columns: 190px 190px 1fr; gap: 28px; }
            .nav-category-panel .th-sidebar-mega.mega-beauty { max-width: 968px; grid-template-columns: minmax(0, 1fr) 220px; }
            .nav-category-panel .th-sidebar-mega.mega-beauty .th-sidebar-mega-content { grid-template-columns: 140px 190px 1fr; gap: 28px 34px; }
            .nav-category-panel .th-sidebar-mega-column h4 { margin: 0 0 14px; font-size: 14px; line-height: 1.35; color: #1f1f1f; text-transform: uppercase; font-weight: 800; }
            .nav-category-panel .th-sidebar-mega-column ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
            .nav-category-panel .th-sidebar-mega-column a { color: #5f5f5f; font-size: 13px; line-height: 1.45; }
            .nav-category-panel .th-sidebar-mega-column a:hover { color: var(--th-red); }
            .nav-category-panel .th-sidebar-mega-promo { display: grid; gap: 8px; padding: 0; background: #fafafa; border-left: 1px solid var(--th-line); }
            .nav-category-panel .th-sidebar-mega-promo a { position: relative; min-height: 69px; overflow: hidden; }
            .nav-category-panel .th-sidebar-mega-promo img { width: 100%; height: 100%; object-fit: cover; }
            .nav-category-panel .th-sidebar-mega-promo span { position: absolute; left: 12px; bottom: 10px; right: 12px; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 10px rgba(0,0,0,0.45); }
            .breadcrumb { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding: 18px 0; font-size: 13px; color: var(--th-muted); }
            .hero { display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(360px, .92fr); gap: 22px; margin-bottom: 20px; }
            .panel { background: var(--th-surface); border: 1px solid var(--th-line); }
            .gallery-panel { padding: 12px; }
            .gallery-stage { position: relative; background: #f7f7f7; border: 1px solid var(--th-line); overflow: hidden; }
            .gallery-stage img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; }
            .gallery-thumbs { display: flex; gap: 10px; margin-top: 12px; overflow-x: auto; padding-bottom: 4px; }
            .gallery-thumb { border: 1px solid var(--th-line); padding: 4px; background: #fff; cursor: pointer; min-width: 74px; }
            .gallery-thumb.is-active { border-color: var(--th-red); box-shadow: inset 0 0 0 1px var(--th-red); }
            .gallery-thumb img { width: 64px; height: 64px; object-fit: cover; }
            .info-panel { padding: 18px 22px 20px; }
            .title { margin: 0 0 10px; font-size: 34px; line-height: 1.2; font-weight: 700; }
            .share { color: var(--th-muted); font-size: 13px; margin-bottom: 8px; }
            .summary { color: #444; font-size: 15px; line-height: 1.7; margin-bottom: 14px; }
            .price-box { border-top: 1px solid var(--th-line); border-bottom: 1px solid var(--th-line); padding: 14px 0; margin-bottom: 14px; }
            .price-note { color: #666; font-size: 15px; margin-bottom: 6px; }
            .price-line { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .deal-price { color: var(--th-red); font-size: 38px; line-height: 1; font-weight: 800; }
            .origin-price { color: #7d7d7d; text-decoration: line-through; font-size: 28px; }
            .discount-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 52px; height: 28px; background: var(--th-red); color: #fff; font-size: 16px; font-weight: 700; border-radius: 4px; }
            .offer-row { display: grid; grid-template-columns: 1fr 138px; gap: 16px; align-items: start; margin-bottom: 14px; }
            .benefit-box { display: grid; gap: 10px; }
            .moneyback { display: inline-flex; align-items: center; gap: 8px; background: var(--th-warm); border: 1px solid #ffd5a6; color: #915700; font-size: 13px; padding: 8px 10px; width: fit-content; }
            .purchase-line { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .purchase-line label { color: #666; font-size: 14px; }
            .purchase-line select { height: 36px; border: 1px solid var(--th-line); padding: 0 8px; background: #fff; }
            .qr-box { border: 1px solid var(--th-line); padding: 10px; text-align: center; font-size: 11px; color: #666; background: #fff; }
            .qr-box img { width: 100%; aspect-ratio: 1 / 1; object-fit: contain; }
            .cta-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
            .btn-primary, .btn-secondary { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 24px; font-weight: 700; font-size: 15px; border-radius: 2px; }
            .btn-primary { background: var(--th-green); color: #fff; min-width: 184px; box-shadow: inset 0 -2px 0 rgba(0,0,0,.14); }
            .btn-primary:hover { background: var(--th-green-dark); }
            .btn-secondary { border: 1px solid #97c96c; color: var(--th-green-dark); background: #fff; min-width: 184px; }
            .btn-favorite { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 18px; border: 1px solid #f3c8c8; background: #fff; color: #b42318; font-weight: 700; cursor: pointer; }
            .btn-favorite.is-active { background: #fff2f2; border-color: #ef2b2d; color: #8f1015; }
            .stats { display: flex; gap: 24px; flex-wrap: wrap; border-top: 1px solid var(--th-line); padding-top: 14px; color: #666; font-size: 14px; }
            .stats strong { color: #2f2f2f; }
            .content-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 18px; margin-bottom: 22px; }
            .info-stack { display: grid; gap: 18px; }
            .section-panel { background: #fff; border: 1px solid var(--th-line); }
            .section-title { margin: 0; padding: 16px 18px 0; font-size: 32px; color: #555; text-transform: uppercase; }
            .section-body { padding: 14px 18px 18px; }
            .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
            .bullet-list { margin: 0; padding-left: 18px; color: #444; line-height: 1.8; }
            .bullet-list li + li { margin-top: 6px; }
            .tabs { display: flex; border-bottom: 1px solid var(--th-line); background: #f0f0f0; }
            .tab-button { border: 0; background: transparent; padding: 14px 18px; font-size: 14px; font-weight: 700; color: #888; cursor: pointer; }
            .tab-button.is-active { background: #fff; color: var(--th-ink); border-top: 3px solid var(--th-green); }
            .tab-panel { display: none; padding: 20px 18px; }
            .tab-panel.is-active { display: block; }
            .detail-copy { color: #444; line-height: 1.9; font-size: 15px; }
            .detail-copy p { margin: 0 0 16px; }
            .guide-intro { display: grid; gap: 16px; }
            .guide-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(240px, .85fr); gap: 18px; padding: 18px; border: 1px solid #e7efe0; background: linear-gradient(135deg, #fbfff7 0%, #fff8ed 100%); }
            .guide-hero-copy h3 { margin: 0 0 10px; font-size: 24px; color: #36511d; }
            .guide-hero-copy p { margin: 0; color: #56703d; line-height: 1.8; }
            .guide-badges { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
            .guide-badge { display: inline-flex; align-items: center; min-height: 34px; padding: 0 12px; border-radius: 999px; background: rgba(101, 179, 46, .12); color: #4b7f1f; font-size: 13px; font-weight: 700; }
            .guide-side-card { border: 1px solid #f0d8ba; background: #fff; padding: 16px; display: grid; gap: 12px; align-content: start; }
            .guide-side-card strong { font-size: 16px; color: #a05c11; }
            .guide-side-card p { margin: 0; color: #7b6a55; line-height: 1.7; font-size: 14px; }
            .guide-steps { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .guide-step-card { position: relative; border: 1px solid var(--th-line); background: #fff; padding: 18px 18px 16px 72px; min-height: 138px; }
            .guide-step-badge { position: absolute; left: 18px; top: 18px; width: 40px; height: 40px; display: grid; place-items: center; background: var(--th-green); color: #fff; font-weight: 800; font-size: 14px; }
            .guide-step-card h4 { margin: 0 0 8px; font-size: 18px; color: #333; }
            .guide-step-card p { margin: 0; color: #666; line-height: 1.8; font-size: 14px; }
            .guide-grid { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 18px; }
            .guide-note-card, .guide-support-card { border: 1px solid var(--th-line); background: #fff; padding: 18px; }
            .guide-note-card h4, .guide-support-card h4 { margin: 0 0 12px; font-size: 18px; color: #444; text-transform: uppercase; }
            .guide-note-list { margin: 0; padding-left: 18px; color: #555; line-height: 1.85; font-size: 14px; }
            .guide-note-list li + li { margin-top: 8px; }
            .guide-support-card { display: grid; gap: 14px; background: linear-gradient(180deg, #ffffff 0%, #fff8f1 100%); }
            .guide-support-block { padding: 12px 14px; border: 1px solid #f2e0c8; background: rgba(255,255,255,.92); }
            .guide-support-block strong { display: block; margin-bottom: 6px; color: #9b5a16; }
            .guide-support-block span { display: block; color: #6d655d; line-height: 1.7; font-size: 14px; }
            .sidebar-card { background: #fff; border: 1px solid var(--th-line); padding: 18px; }
            .sidebar-card h3 { margin: 0 0 14px; font-size: 24px; text-transform: uppercase; color: #555; }
            .sidebar-related { display: grid; gap: 14px; }
            .sidebar-related-card { display: grid; grid-template-columns: 86px 1fr; gap: 12px; align-items: start; padding-bottom: 14px; border-bottom: 1px solid var(--th-line); }
            .sidebar-related-card:last-child { padding-bottom: 0; border-bottom: 0; }
            .sidebar-related-card img { width: 86px; height: 86px; object-fit: cover; border: 1px solid var(--th-line); }
            .sidebar-related-card h4 { margin: 0 0 8px; font-size: 14px; line-height: 1.45; }
            .sidebar-related-meta { display: grid; gap: 6px; }
            .sidebar-related-price { color: var(--th-red); font-weight: 800; font-size: 22px !important; line-height: 1.2; }
            .sidebar-related-pricing { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .sidebar-related-old-price { color: #8a8a8a; font-size: 13px; text-decoration: line-through; }
            .sidebar-related-discount { display: inline-flex; align-items: center; justify-content: center; min-width: 42px; height: 22px; padding: 0 6px; border-radius: 4px; background: var(--th-red); color: #fff; font-size: 12px; font-weight: 800; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
            .th-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }
            @media (max-width: 1080px) {
                .hero, .content-grid, .two-col { grid-template-columns: 1fr; }
                .offer-row { grid-template-columns: 1fr; }
                .header-main { grid-template-columns: 1fr; }
                .nav-category-panel .th-sidebar-mega { display: none !important; }
            }
            @media (max-width: 720px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .nav-inner { display: block; }
                .nav-category { min-width: 0; }
                .nav-category-panel { width: min(320px, calc(100vw - 16px)); }
                .nav-links { padding: 10px 0 12px; gap: 16px; }
                .title { font-size: 28px; }
                .deal-price { font-size: 34px; }
                .origin-price { font-size: 22px; }
                .section-title, .sidebar-card h3 { font-size: 24px; }
                .tabs { overflow-x: auto; }
                .guide-hero, .guide-grid, .guide-steps { grid-template-columns: 1fr; }
                .guide-step-card { padding-left: 18px; padding-top: 72px; }
                .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-footer-grid { grid-template-columns: 1fr; }
            }
            .interior-product-page { background: #f7f5f2; }
            .interior-product-page .utility { background: #fdfbf8; border-bottom-color: #eadfda; }
            .interior-product-page .header { background: #fffaf6; border-bottom: 1px solid #eadfda; }
            .interior-product-page .searchbar { border: 1px solid #1f1a1d; border-radius: 0; }
            .interior-product-page .searchbar button, .interior-product-page .nav { background: #1f1a1d; }
            .interior-product-page .nav-category { background: #b20f3a; letter-spacing: .08em; }
            .interior-product-page .nav-links a { letter-spacing: .08em; font-size: 12px; }
            .interior-product-page .breadcrumb { color: #756a70; }
            .interior-product-page .hero { grid-template-columns: minmax(0, .96fr) minmax(420px, .76fr); gap: 30px; align-items: start; margin-bottom: 28px; }
            .interior-product-page .panel { border: 0; background: transparent; }
            .interior-product-page .gallery-panel { padding: 0; }
            .interior-product-page .gallery-stage { border: 0; background: #ded4cf; }
            .interior-product-page .gallery-stage img { aspect-ratio: 4 / 5; }
            .interior-product-page .gallery-thumbs { margin-top: 14px; }
            .interior-product-page .gallery-thumb { border: 1px solid #d8cbc6; background: #fffaf6; border-radius: 0; }
            .interior-product-page .gallery-thumb.is-active { border-color: #1f1a1d; box-shadow: inset 0 0 0 1px #1f1a1d; }
            .interior-product-page .info-panel { background: #fffaf6; padding: clamp(26px, 4vw, 44px); box-shadow: 0 18px 44px rgba(31,26,29,.08); }
            .interior-product-page .title { font-family: Georgia, 'Times New Roman', serif; font-weight: 500; font-size: clamp(34px, 5vw, 58px); line-height: 1; color: #1f1a1d; }
            .interior-product-page .share { color: #b20f3a; letter-spacing: .14em; text-transform: uppercase; font-size: 11px; }
            .interior-product-page .summary { color: #5f555a; }
            .interior-product-page .price-box { border: 0; border-top: 1px solid #eadfda; border-bottom: 1px solid #eadfda; }
            .interior-product-page .deal-price { color: #b20f3a; font-family: Georgia, 'Times New Roman', serif; font-weight: 500; }
            .interior-product-page .discount-badge, .interior-product-page .btn-primary { border-radius: 0; background: #1f1a1d; }
            .interior-product-page .btn-secondary, .interior-product-page .btn-favorite { border-radius: 0; border-color: #1f1a1d; color: #1f1a1d; background: transparent; }
            .interior-product-page .moneyback { border-color: #f2c94c; background: #fff8d8; color: #7a5a00; }
            .interior-fit-panel { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 16px 0; }
            .interior-fit-chip { min-height: 48px; display: grid; place-items: center; border: 1px solid #eadfda; background: #fff; color: #1f1a1d; font-size: 12px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; text-align: center; }
            .interior-styling-note { margin-top: 14px; padding: 14px 16px; background: #1f1a1d; color: rgba(255,255,255,.78); line-height: 1.7; }
            .interior-styling-note strong { display: block; color: #f2c94c; margin-bottom: 4px; letter-spacing: .12em; text-transform: uppercase; font-size: 12px; }
            .interior-product-page .stats { border-top-color: #eadfda; color: #756a70; }
            .interior-product-page .content-grid { grid-template-columns: minmax(0, 1fr) 340px; gap: 26px; }
            .interior-product-page .section-panel, .interior-product-page .sidebar-card { border: 0; background: #fffaf6; box-shadow: 0 14px 38px rgba(31,26,29,.07); }
            .interior-product-page .section-title, .interior-product-page .sidebar-card h3 { font-family: Georgia, 'Times New Roman', serif; font-size: 34px; text-transform: none; font-weight: 500; color: #1f1a1d; }
            .interior-product-page .tab-button { letter-spacing: .08em; text-transform: uppercase; }
            .interior-product-page .tab-button.is-active { border-top-color: #b20f3a; }
            .interior-product-page .guide-hero { border: 0; background: #1f1a1d; color: #fff; }
            .interior-product-page .guide-hero-copy h3 { color: #f2c94c; font-family: Georgia, 'Times New Roman', serif; font-weight: 500; }
            .interior-product-page .guide-hero-copy p { color: rgba(255,255,255,.72); }
            .interior-product-page .guide-badge { border-radius: 0; background: rgba(242,201,76,.16); color: #f2c94c; }
            .interior-product-page .guide-step-badge { background: #b20f3a; }
            .interior-product-page .sidebar-related-card img { height: 112px; object-fit: cover; }
            .interior-product-page .th-footer { background: #1f1a1d; color: #fff; border: 0; }
            .interior-product-page .th-footer-card h4, .interior-product-page .th-company strong { color: #f2c94c; letter-spacing: .12em; }
            .interior-product-page .th-footer-links { color: rgba(255,255,255,.68); }
            .interior-product-page .th-company { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 0; }
            @media (max-width: 1080px) {
                .interior-product-page .hero, .interior-product-page .content-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 720px) {
                .interior-fit-panel { grid-template-columns: 1fr; }
            }
            @include('theme-th0020::partials.interior-shell-styles')
        </style>
    </head>
    <body class="th-interior-page interior-product-page">
        @include('theme-th0020::partials.interior-header')
        <div class="th-legacy-header" hidden>
        <div class="utility">
            <div class="wrap utility-inner">
                <div class="utility-group">
                    <span>{{ $contactLocation }}</span>
                    <button type="button" class="utility-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}</button>
                </div>
                <div class="utility-actions">
                    <span>@themeT('common.hotline_label', 'Hotline'): {{ $contactHotline }}</span>
                    <span>@themeT('common.email_label', 'Email'): {{ $contactEmail }}</span>
                    @if (!empty($customerAuth['is_authenticated']))
                        <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">@themeT('common.account', 'Tài kho?n')</a>
                        <form class="utility-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                            @csrf
                            <button type="submit" class="utility-action">@themeT('common.logout', 'Ðang xu?t')</button>
                        </form>
                    @else
                        <button type="button" class="utility-action" data-open-auth-modal="register">@themeT('common.register', 'Ðang ký')</button>
                        <button type="button" class="utility-action" data-open-auth-modal="login">@themeT('common.login', 'Ðang nh?p')</button>
                    @endif
                </div>
            </div>
        </div>

        <header class="header">
            <div class="wrap header-main">
                <a class="brand" href="{{ route('site.home') }}">
                    <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ $companyTitle ?: '' }}">
                </a>

                <form class="searchbar" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('common.search_placeholder', 'Tìm ki?m s?n ph?m / khuy?n mãi')" aria-label="@themeT('common.search_aria', 'Tìm ki?m s?n ph?m')" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                    <button type="submit" aria-label="{{ $t('common.search_button', 'Tìm') }}">?</button>
                </form>

                <a class="cart-link" href="{{ route('site.cart.index') }}">@themeT('common.cart_label', 'GI? HÀNG') ({{ $cartSummary['count'] ?? 0 }})</a>
            </div>
        </header>

        <nav class="nav">
            <div class="wrap nav-inner">
                <div class="nav-category-wrap">
                    <div class="nav-category">@themeT('common.categories', 'DANH M?C')</div>
                    <div class="nav-category-panel">
                        @foreach ($productMenu as $item)
                            <div class="th-sidebar-entry">
                                <a href="{{ $item['url'] ?? route('site.catalog.search') }}" target="{{ $item['target'] ?? '_self' }}" class="th-sidebar-item {{ !empty($item['highlight']) ? 'is-accent' : '' }}">
                                    <span><span class="th-sidebar-icon">{{ $item['icon'] ?? '?' }}</span> {{ $item['label'] ?? __('common.category') }}</span>
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
                                                            <li><a href="{{ $child['url'] ?? ($item['url'] ?? route('site.catalog.search')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.child_group') }}</a></li>
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
                <div class="nav-links">
                    @foreach ($topMenu as $item)
                        <a href="{{ $item['url'] ?? route('site.home') }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? __('common.menu') }}</a>
                    @endforeach
                </div>
            </div>
        </nav>
        </div>

        <main class="wrap">
            @if (session('cart_success'))
                <div class="flash-banner">{{ session('cart_success') }}</div>
            @endif

            <div class="breadcrumb">
                <a href="{{ route('site.home') }}">@themeT('common.home', 'Trang ch?')</a>
                @if ($productModel->category?->parent)
                    <span>›</span>
                    <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->parent->slug]) }}">{{ $productModel->category->parent->name }}</a>
                @endif
                @if ($productModel->category)
                    <span>›</span>
                    <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->slug]) }}">{{ $productModel->category->name }}</a>
                @endif
                <span>›</span>
                <span>{{ $product['title'] }}</span>
            </div>

            <section class="hero">
                <div class="panel gallery-panel">
                    <div class="gallery-stage">
                        <img id="th-product-main-image" src="{{ $primaryImage }}" alt="{{ $product['title'] }}">
                    </div>
                    @if (count($gallery) > 1)
                        <div class="gallery-thumbs" aria-label="@themeT('product.gallery_aria', 'Gallery ?nh s?n ph?m')">
                            @foreach ($gallery as $index => $image)
                                <button type="button" class="gallery-thumb {{ $index === 0 ? 'is-active' : '' }}" data-gallery-thumb data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] }}" aria-label="{{ __('product.gallery_image', ['index' => $index + 1]) }}">
                                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="panel info-panel" id="deal-purchase">
                    <h1 class="title">{{ $product['title'] }}</h1>
                    <div class="share">@themeT('product.share', 'Chia s? s?n ph?m')</div>
                    <div class="summary">{{ $productModel->short_description ?: __('product.summary_fallback') }}</div>

                    <div class="interior-fit-panel" aria-label="@themeT('product.fit_options', 'G?i ý ch?n size')">
                        <span class="interior-fit-chip">@themeT('product.fit_size', 'Size ready')</span>
                        <span class="interior-fit-chip">@themeT('product.fit_mix', 'Easy mix')</span>
                        <span class="interior-fit-chip">@themeT('product.fit_season', 'Season edit')</span>
                    </div>

                    <div class="price-box">
                        @if (($product['old_price'] ?? null) !== null)
                            <div class="price-note">@themeT('product.origin_price', 'Giá g?c:') <span class="origin-price">{{ $formatCurrency($product['old_price']) }}</span></div>
                        @endif
                        <div class="price-line">
                            <span class="deal-price">{{ $formatCurrency($product['price'] ?? null) }}</span>
                            @if ($discount > 0)
                                <span class="discount-badge">-{{ $discount }}%</span>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                        @csrf
                        <div class="offer-row">
                            <div class="benefit-box">
                                <div class="moneyback">{{ __('product.moneyback', ['amount' => number_format(max(1000, (int) round((float) ($product['price'] ?? 0) * 0.015)), 0, ',', '.').'d']) }}</div>
                                <div class="purchase-line">
                                    <label for="deal-quantity">@themeT('product.quantity', 'S? lu?ng')</label>
                                    <select id="deal-quantity" name="quantity">
                                        @foreach (range(1, $maxPurchaseQuantity) as $qty)
                                            <option value="{{ $qty }}">{{ $qty }}</option>
                                        @endforeach
                                    </select>
                                    <span>@themeT('product.evouchers', 'E-Voucher')</span>
                                </div>
                            </div>

                            <div class="qr-box">
                                <img src="https://quickchart.io/qr?size=180&text={{ $qrPayload }}" alt="QR {{ $product['title'] }}">
                                <div>@themeT('product.qrpay', 'Quét mua b?ng QRPay')</div>
                            </div>
                        </div>

                        <div class="cta-row">
                            <button type="submit" class="btn-primary" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}">@themeT('product.buy_now', 'MUA NGAY ?')</button>
                            <button type="submit" class="btn-secondary">@themeT('product.add_to_cart', 'THÊM VÀO GI? HÀNG')</button>
                            @if (!empty($customerAuth['is_authenticated']))
                                <button type="submit" class="btn-favorite {{ !empty($isFavorite) ? 'is-active' : '' }}" formaction="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">{{ !empty($isFavorite) ? __('product.favorite_saved') : __('product.favorite_save') }}</button>
                            @else
                                <button type="button" class="btn-favorite" data-open-auth-modal="login">@themeT('product.login_to_favorite', 'Ðang nh?p d? luu yêu thích')</button>
                            @endif
                        </div>
                    </form>

                    <div class="stats">
                        <span>{{ __('product.sold', ['count' => number_format($soldCount, 0, ',', '.')]) }}</span>
                        <span>{{ __('product.remaining', ['count' => number_format((int) ($product['meta'] ?? 0), 0, ',', '.')]) }}</span>
                        <span data-countdown-wrapper data-deadline="{{ $deadline }}"><strong data-countdown-label>{{ $deadline ? __('product.counting') : __('product.unlimited') }}</strong></span>
                    </div>

                    <div class="interior-styling-note">
                        <strong>@themeT('product.styling_note_title', 'Styling note')</strong>
                        <span>@themeT('product.styling_note_body', 'G?i ý ph?i cùng ph? ki?n t?i gi?n, giày trung tính ho?c layer nh? d? gi? t?ng th? g?n và h?p nhi?u b?i c?nh.')</span>
                    </div>
                </div>
            </section>

            <section class="content-grid">
                <div class="info-stack">
                    <section class="section-panel">
                        <h2 class="section-title">@themeT('product.highlights', 'Ði?m n?i b?t')</h2>
                        <div class="section-body">
                            <ul class="bullet-list">
                                @foreach ($highlights as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </section>

                    <section class="section-panel" id="th-detail-tabs">
                        <div class="tabs" role="tablist">
                            <button type="button" class="tab-button is-active" data-tab-button data-tab-target="detail-copy-panel" role="tab" aria-selected="true">@themeT('product.detail_tab', 'THÔNG TIN CHI TI?T')</button>
                            <button type="button" class="tab-button" data-tab-button data-tab-target="order-guide-panel" role="tab" aria-selected="false">@themeT('product.order_guide_tab', 'HU?NG D?N Ð?T HÀNG')</button>
                        </div>

                        <div id="detail-copy-panel" class="tab-panel is-active" data-tab-panel>
                            <div class="detail-copy">
                                @foreach ($detailParagraphsList as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </div>
                        </div>

                        <div id="order-guide-panel" class="tab-panel" data-tab-panel>
                            <div class="guide-intro">
                                <div class="guide-hero">
                                    <div class="guide-hero-copy">
                                        <h3>{{ __('product.guide_step_4_title') }}</h3>
                                        <p>{{ $t('product.guide_intro', 'Quy trình mua th?i trang trên TH0020 du?c t?i uu theo hu?ng ít bu?c, d? ki?m tra và thu?n ti?n khi mua online ho?c nh?n hàng t?i c?a hàng.') }}</p>
                                        <div class="guide-badges">
                                            <span class="guide-badge">{{ $t('product.guide_badge_quick_confirm', 'Xác nh?n don nhanh') }}</span>
                                            <span class="guide-badge">{{ $t('product.guide_badge_flexible_payment', 'Thanh toán linh ho?t') }}</span>
                                            <span class="guide-badge">{{ $t('product.guide_badge_post_support', 'H? tr? sau mua') }}</span>
                                        </div>
                                    </div>

                                    <div class="guide-side-card">
                                        <strong>{{ $t('product.guide_checkout_notice_title', 'Luu ý tru?c khi thanh toán') }}</strong>
                                        <p>{{ $t('product.guide_checkout_notice_body', 'Hãy ki?m tra k? giá bán, s? lu?ng, th?i h?n uu dãi và thông tin nh?n voucher d? tránh phát sinh thay d?i sau khi don dã du?c xác nh?n.') }}</p>
                                    </div>
                                </div>

                                <div class="guide-steps">
                                    @foreach ($orderGuideSteps as $step)
                                        <article class="guide-step-card">
                                            <div class="guide-step-badge">{{ $step['step'] }}</div>
                                            <h4>{{ $step['title'] }}</h4>
                                            <p>{{ $step['body'] }}</p>
                                        </article>
                                    @endforeach
                                </div>

                                <div class="guide-grid">
                                    <section class="guide-note-card">
                                        <h4>{{ $t('product.guide_note_title', 'Nh?ng di?u nên ki?m tra') }}</h4>
                                        <ul class="guide-note-list">
                                            @foreach ($orderGuideNotes as $note)
                                                <li>{{ $note }}</li>
                                            @endforeach
                                        </ul>
                                    </section>

                                    <aside class="guide-support-card">
                                        <h4>{{ $t('product.guide_support_title', 'H? tr? don hàng') }}</h4>
                                        <div class="guide-support-block">
                                            <strong>{{ $t('product.guide_support_payment_title', 'Thanh toán') }}</strong>
                                            <span>{{ $t('product.guide_support_payment_body', 'Quét QRPay ho?c làm theo hu?ng d?n trên website d? h? th?ng ghi nh?n giao d?ch.') }}</span>
                                        </div>
                                        <div class="guide-support-block">
                                            <strong>{{ $t('product.guide_support_voucher_title', 'Nh?n voucher') }}</strong>
                                            <span>{{ $t('product.guide_support_voucher_body', 'Mã voucher ho?c thông tin don s? du?c g?i v? email / tài kho?n ngay sau khi x? lý thành công.') }}</span>
                                        </div>
                                        <div class="guide-support-block">
                                            <strong>{{ $t('product.guide_support_contact_title', 'Liên h? nhanh') }}</strong>
                                            <span>Hotline: {{ $contactHotline }}<br>Email: {{ $contactEmail }}</span>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="sidebar-card">
                    <h3>{{ $t('product.related_products', 'S?n ph?m liên quan') }}</h3>
                    @if ($relatedProducts !== [])
                        <div class="sidebar-related">
                            @foreach (array_slice($relatedProducts, 0, 4) as $item)
                                <article class="sidebar-related-card">
                                    <a href="{{ $item['url'] }}"><img src="{{ $item['image'] }}" alt="{{ $item['title'] }}"></a>
                                    <div class="sidebar-related-meta">
                                        <h4><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h4>
                                        <div class="sidebar-related-price">{{ $formatCurrency($item['price'] ?? null) }}</div>
                                        <div class="sidebar-related-pricing">
                                            @if (($item['old_price'] ?? null) !== null)
                                                <span class="sidebar-related-old-price">{{ $formatCurrency($item['old_price']) }}</span>
                                            @endif
                                            @if (($item['discount'] ?? 0) > 0)
                                                <span class="sidebar-related-discount">-{{ (int) $item['discount'] }}%</span>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="detail-copy">
                            <p>{{ $t('product.related_empty', 'Chua có s?n ph?m liên quan trong cùng danh m?c.') }}</p>
                        </div>
                    @endif
                </aside>
            </section>
        </main>

        @include('theme-th0020::partials.footer', ['footerContainerClass' => 'wrap'])

        @include('theme-th0020::partials.product-search-autocomplete')
        @include('theme-th0020::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])

        <script>
            document.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = document.getElementById('th-product-main-image');

                    if (!target) {
                        return;
                    }

                    target.src = button.dataset.imageUrl || target.src;
                    target.alt = button.dataset.imageAlt || target.alt;

                    document.querySelectorAll('[data-gallery-thumb]').forEach((item) => item.classList.remove('is-active'));
                    button.classList.add('is-active');
                });
            });

            document.querySelectorAll('[data-tab-button]').forEach((button) => {
                button.addEventListener('click', () => {
                    const targetId = button.dataset.tabTarget;

                    document.querySelectorAll('[data-tab-button]').forEach((item) => {
                        item.classList.toggle('is-active', item === button);
                        item.setAttribute('aria-selected', item === button ? 'true' : 'false');
                    });

                    document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                        panel.classList.toggle('is-active', panel.id === targetId);
                    });
                });
            });

            const countdownNode = document.querySelector('[data-countdown-wrapper]');
            const countdownLabel = document.querySelector('[data-countdown-label]');

            if (countdownNode && countdownLabel) {
                const deadline = countdownNode.dataset.deadline ? new Date(countdownNode.dataset.deadline) : null;

                if (!deadline || Number.isNaN(deadline.getTime())) {
                    countdownLabel.textContent = @json($t('product.countdown_unlimited', 'Không gi?i h?n th?i gian'));
                } else {
                    const tick = () => {
                        const diff = deadline.getTime() - Date.now();

                        if (diff <= 0) {
                            countdownLabel.textContent = @json($t('product.countdown_finished', 'Uu dãi dã k?t thúc'));
                            return;
                        }

                        const totalSeconds = Math.floor(diff / 1000);
                        const days = Math.floor(totalSeconds / 86400);
                        const hours = Math.floor((totalSeconds % 86400) / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const seconds = totalSeconds % 60;

                        countdownLabel.textContent = @json($t('product.countdown_format', ':days ngày :hours : :minutes : :seconds'))
                            .replace(':days', days)
                            .replace(':hours', String(hours).padStart(2, '0'))
                            .replace(':minutes', String(minutes).padStart(2, '0'))
                            .replace(':seconds', String(seconds).padStart(2, '0'));
                    };

                    tick();
                    window.setInterval(tick, 1000);
                }
            }
        </script>
    </body>
</html>
