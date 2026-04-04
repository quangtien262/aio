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
    $gallery = $productGallery ?? [];
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $footerColumns = [
        'Trợ giúp' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership'],
        'Giới thiệu' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động'],
        'Hợp tác' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí'],
    ];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/th0001-product-fallback/960/720');
    $discount = (int) ($product['discount'] ?? 0);
    $soldCount = (int) ($productModel->sold_count ?? 0);
    $deadline = $productModel->deal_end_at?->toIso8601String();
    $qrPayload = rawurlencode(($productModel->sku ?? 'AIO').'-'.($productModel->slug ?? $productModel->id));
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $maxPurchaseQuantity = $productModel->stock !== null && (int) $productModel->stock > 0
        ? max(1, min(5, (int) $productModel->stock))
        : 5;
    $orderGuideSteps = [
        [
            'step' => '01',
            'title' => 'Chọn gói phù hợp',
            'body' => 'Chọn số lượng, xem kỹ giá bán, mức giảm và thời hạn áp dụng trước khi xác nhận đơn hàng.',
        ],
        [
            'step' => '02',
            'title' => 'Xác nhận và thanh toán',
            'body' => 'Bấm Mua ngay hoặc quét QRPay để thanh toán nhanh. Hệ thống sẽ ghi nhận đơn ngay sau khi giao dịch hoàn tất.',
        ],
        [
            'step' => '03',
            'title' => 'Nhận voucher / thông tin đơn',
            'body' => 'Mã đơn hoặc E-Voucher sẽ được gửi về email, tài khoản hoặc trang quản lý đơn hàng của bạn.',
        ],
        [
            'step' => '04',
            'title' => 'Sử dụng và được hỗ trợ',
            'body' => 'Xuất trình voucher khi sử dụng dịch vụ. Nếu cần đổi lịch hoặc hỗ trợ phát sinh, liên hệ hotline để được xử lý nhanh.',
        ],
    ];
    $orderGuideNotes = [
        'Ưu tiên kiểm tra thời hạn deal, điều kiện áp dụng và số lượng còn lại trước khi thanh toán.',
        'Với E-Voucher, khách hàng nên lưu lại mã đơn trong email hoặc chụp màn hình để dùng khi cần.',
        'Nếu thanh toán thành công nhưng chưa nhận thông tin đơn, vui lòng chờ vài phút rồi kiểm tra lại email / tài khoản.',
    ];

    if ($highlights === [] && filled($productModel->short_description)) {
        $highlights = [trim((string) $productModel->short_description)];
    }

    if ($detailParagraphsList === []) {
        $detailParagraphsList = [
            $productModel->short_description ?: 'Sản phẩm đang dùng mô tả mặc định. Sếp có thể cập nhật nội dung chi tiết ngay trong admin Catalog.',
            'Trang chi tiết này đã hỗ trợ gallery nhiều ảnh, nội dung dạng deal và các block thông tin dài để theme TH0001 bám sát bố cục trang deal thương mại điện tử.',
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $product['title'] }} | {{ data_get($branding, 'company_name', 'TH0001') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-red-dark: #d91f26;
                --th-green: #65b32e;
                --th-green-dark: #4e9620;
                --th-ink: #202124;
                --th-muted: #70757f;
                --th-line: #e6e6e6;
                --th-bg: #f3f3f3;
                --th-surface: #ffffff;
                --th-warm: #fff5e9;
            }
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
            .nav-links a { text-align: left; text-transform: uppercase; }
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
        </style>
    </head>
    <body>
        <div class="utility">
            <div class="wrap utility-inner">
                <div class="utility-group">
                    <span>{{ $contactLocation }}</span>
                    <button type="button" class="utility-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? 'Đã đăng ký bản tin' : 'Đăng ký bản tin' }}</button>
                </div>
                <div class="utility-actions">
                    <span>Hotline: {{ $contactHotline }}</span>
                    <span>Email: {{ $contactEmail }}</span>
                    @if (!empty($customerAuth['is_authenticated']))
                        <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>
                        <form class="utility-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                            @csrf
                            <button type="submit" class="utility-action">Đăng xuất</button>
                        </form>
                    @else
                        <button type="button" class="utility-action" data-open-auth-modal="register">Đăng ký</button>
                        <button type="button" class="utility-action" data-open-auth-modal="login">Đăng nhập</button>
                    @endif
                </div>
            </div>
        </div>

        <header class="header">
            <div class="wrap header-main">
                <a class="brand" href="/">
                    <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'TH0001') }}">
                </a>

                <div class="searchbar" role="search">
                    <input type="text" value="{{ $product['title'] }}" aria-label="Tìm kiếm" readonly>
                    <button type="button" aria-label="Tìm kiếm">⌕</button>
                </div>

                <a class="cart-link" href="{{ route('site.cart.index') }}">GIỎ HÀNG ({{ $cartSummary['count'] ?? 0 }})</a>
            </div>
        </header>

        <nav class="nav">
            <div class="wrap nav-inner">
                <div class="nav-category-wrap">
                    <div class="nav-category">DANH MỤC</div>
                    <div class="nav-category-panel">
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
                <div class="nav-links">
                    @foreach ($topMenu as $item)
                        <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                    @endforeach
                </div>
            </div>
        </nav>

        <main class="wrap">
            @if (session('cart_success'))
                <div class="flash-banner">{{ session('cart_success') }}</div>
            @endif

            <div class="breadcrumb">
                <a href="/">Trang chủ</a>
                @if ($productModel->category?->parent)
                    <span>›</span>
                    <a href="/danh-muc/{{ $productModel->category->parent->slug }}">{{ $productModel->category->parent->name }}</a>
                @endif
                @if ($productModel->category)
                    <span>›</span>
                    <a href="/danh-muc/{{ $productModel->category->slug }}">{{ $productModel->category->name }}</a>
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
                        <div class="gallery-thumbs" aria-label="Gallery ảnh sản phẩm">
                            @foreach ($gallery as $index => $image)
                                <button type="button" class="gallery-thumb {{ $index === 0 ? 'is-active' : '' }}" data-gallery-thumb data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] }}" aria-label="Ảnh {{ $index + 1 }}">
                                    <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="panel info-panel" id="deal-purchase">
                    <h1 class="title">{{ $product['title'] }}</h1>
                    <div class="share">Chia sẻ deal</div>
                    <div class="summary">{{ $productModel->short_description ?: 'Voucher ưu đãi đang được hiển thị theo cấu trúc catalog thật trong hệ thống AIO.' }}</div>

                    <div class="price-box">
                        @if (($product['old_price'] ?? null) !== null)
                            <div class="price-note">Giá gốc: <span class="origin-price">{{ $formatCurrency($product['old_price']) }}</span></div>
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
                                <div class="moneyback">Hoàn đến {{ number_format(max(1000, (int) round((float) ($product['price'] ?? 0) * 0.015)), 0, ',', '.') }}đ vào tài khoản</div>
                                <div class="purchase-line">
                                    <label for="deal-quantity">Số lượng</label>
                                    <select id="deal-quantity" name="quantity">
                                        @foreach (range(1, $maxPurchaseQuantity) as $qty)
                                            <option value="{{ $qty }}">{{ $qty }}</option>
                                        @endforeach
                                    </select>
                                    <span>E-Voucher</span>
                                </div>
                            </div>

                            <div class="qr-box">
                                <img src="https://quickchart.io/qr?size=180&text={{ $qrPayload }}" alt="QR {{ $product['title'] }}">
                                <div>Quét mua bằng QRPay</div>
                            </div>
                        </div>

                        <div class="cta-row">
                            <button type="submit" class="btn-primary" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}">MUA NGAY →</button>
                            <button type="submit" class="btn-secondary">THÊM VÀO GIỎ HÀNG</button>
                            @if (!empty($customerAuth['is_authenticated']))
                                <button type="submit" class="btn-favorite {{ !empty($isFavorite) ? 'is-active' : '' }}" formaction="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">{{ !empty($isFavorite) ? 'Đã lưu yêu thích' : 'Lưu yêu thích' }}</button>
                            @else
                                <button type="button" class="btn-favorite" data-open-auth-modal="login">Đăng nhập để lưu yêu thích</button>
                            @endif
                        </div>
                    </form>

                    <div class="stats">
                        <span><strong>{{ number_format($soldCount, 0, ',', '.') }}</strong> đã mua</span>
                        <span><strong>{{ number_format((int) ($product['meta'] ?? 0), 0, ',', '.') }}</strong> còn lại</span>
                        <span data-countdown-wrapper data-deadline="{{ $deadline }}"><strong data-countdown-label>{{ $deadline ? 'Đang tính' : 'Không giới hạn' }}</strong></span>
                    </div>
                </div>
            </section>

            <section class="content-grid">
                <div class="info-stack">
                    <section class="section-panel">
                        <h2 class="section-title">Điểm nổi bật</h2>
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
                            <button type="button" class="tab-button is-active" data-tab-button data-tab-target="detail-copy-panel" role="tab" aria-selected="true">THÔNG TIN CHI TIẾT</button>
                            <button type="button" class="tab-button" data-tab-button data-tab-target="order-guide-panel" role="tab" aria-selected="false">HƯỚNG DẪN ĐẶT HÀNG</button>
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
                                        <h3>Đặt hàng nhanh, nhận voucher gọn, sử dụng thuận tiện</h3>
                                        <p>Quy trình mua deal trên TH0001 được tối ưu theo hướng ít bước, dễ kiểm tra và thuận tiện khi dùng tại cửa hàng hoặc nhận E-Voucher online.</p>
                                        <div class="guide-badges">
                                            <span class="guide-badge">Xác nhận đơn nhanh</span>
                                            <span class="guide-badge">Thanh toán linh hoạt</span>
                                            <span class="guide-badge">Hỗ trợ sau mua</span>
                                        </div>
                                    </div>

                                    <div class="guide-side-card">
                                        <strong>Lưu ý trước khi thanh toán</strong>
                                        <p>Hãy kiểm tra kỹ giá bán, số lượng, thời hạn ưu đãi và thông tin nhận voucher để tránh phát sinh thay đổi sau khi đơn đã được xác nhận.</p>
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
                                        <h4>Những điều nên kiểm tra</h4>
                                        <ul class="guide-note-list">
                                            @foreach ($orderGuideNotes as $note)
                                                <li>{{ $note }}</li>
                                            @endforeach
                                        </ul>
                                    </section>

                                    <aside class="guide-support-card">
                                        <h4>Hỗ trợ đơn hàng</h4>
                                        <div class="guide-support-block">
                                            <strong>Thanh toán</strong>
                                            <span>Quét QRPay hoặc làm theo hướng dẫn trên website để hệ thống ghi nhận giao dịch.</span>
                                        </div>
                                        <div class="guide-support-block">
                                            <strong>Nhận voucher</strong>
                                            <span>Mã voucher hoặc thông tin đơn sẽ được gửi về email / tài khoản ngay sau khi xử lý thành công.</span>
                                        </div>
                                        <div class="guide-support-block">
                                            <strong>Liên hệ nhanh</strong>
                                            <span>Hotline: {{ $contactHotline }}<br>Email: {{ $contactEmail }}</span>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="sidebar-card">
                    <h3>Sản phẩm liên quan</h3>
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
                            <p>Chưa có sản phẩm liên quan trong cùng danh mục.</p>
                        </div>
                    @endif
                </aside>
            </section>
        </main>

        <footer class="th-footer">
            <div class="wrap th-footer-inner">
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

        @include('theme-th0001::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])

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
                    countdownLabel.textContent = 'Không giới hạn thời gian';
                } else {
                    const tick = () => {
                        const diff = deadline.getTime() - Date.now();

                        if (diff <= 0) {
                            countdownLabel.textContent = 'Ưu đãi đã kết thúc';
                            return;
                        }

                        const totalSeconds = Math.floor(diff / 1000);
                        const days = Math.floor(totalSeconds / 86400);
                        const hours = Math.floor((totalSeconds % 86400) / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const seconds = totalSeconds % 60;

                        countdownLabel.textContent = `${days} ngày ${String(hours).padStart(2, '0')} : ${String(minutes).padStart(2, '0')} : ${String(seconds).padStart(2, '0')}`;
                    };

                    tick();
                    window.setInterval(tick, 1000);
                }
            }
        </script>
    </body>
</html>
