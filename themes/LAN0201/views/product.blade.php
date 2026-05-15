@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('LAN0201', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0909 020 201');
    $contactEmail = data_get($branding, 'support_email', 'sales@lan0201.demo');
    $contactLocation = data_get($branding, 'support_location', 'TP.HCM - hành lang mở bán khu Đông');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $gallery = $productGallery ?? [];
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $footerColumns = [
        $t('footer.help_title', 'Thông tin dự án') => [$t('footer.shipping_policy', 'Tổng quan vị trí'), $t('footer.payment_methods', 'Hướng dẫn đặt lịch'), $t('footer.evouchers', 'Bảng giá và ưu đãi'), $t('footer.membership', 'Chính sách thanh toán')],
        $t('footer.about_title', 'Hỗ trợ giao dịch') => [$t('footer.about_us', 'Mặt bằng tổng thể'), $t('footer.contact', 'Liên hệ phòng kinh doanh'), $t('footer.privacy_policy', 'Cam kết pháp lý'), $t('footer.operating_regulations', 'Câu hỏi thường gặp')],
        $t('footer.partnership_title', 'Kết nối tư vấn') => [$t('footer.gift_cards', 'Đăng ký nhận tư vấn'), $t('footer.partner_contact', 'Tải brochure dự án'), $t('footer.careers', 'Nhận thông tin mở bán'), $t('footer.press_info', 'Chính sách bảo mật')],
    ];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/LAN0201-product-fallback/960/720');
    $discount = (int) ($product['discount'] ?? 0);
    $soldCount = (int) ($productModel->sold_count ?? 0);
    $deadline = $productModel->deal_end_at?->toIso8601String();
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $maxPurchaseQuantity = $productModel->stock !== null && (int) $productModel->stock > 0 ? max(1, min(5, (int) $productModel->stock)) : 5;
    $orderGuideSteps = [
        ['step' => '01', 'title' => $t('product.guide_step_1_title', 'Xác định nhu cầu và căn quan tâm'), 'body' => $t('product.guide_step_1_body', 'Chốt khu vực, loại hình sản phẩm, mức giá và mục đích mua ở hoặc đầu tư để đội ngũ tư vấn gửi bảng hàng phù hợp.')],
        ['step' => '02', 'title' => $t('product.guide_step_2_title', 'Nhận bảng giá và chính sách'), 'body' => $t('product.guide_step_2_body', 'Bộ phận kinh doanh gửi bảng giá, chính sách thanh toán, ưu đãi theo đợt mở bán và danh sách sản phẩm còn hàng.')],
        ['step' => '03', 'title' => $t('product.guide_step_3_title', 'Đặt lịch xem nhà mẫu'), 'body' => $t('product.guide_step_3_body', 'Sau khi chọn được sản phẩm phù hợp, khách có thể hẹn tham quan nhà mẫu, vị trí thực tế hoặc làm việc trực tiếp với bộ phận kinh doanh.')],
        ['step' => '04', 'title' => $t('product.guide_step_4_title', 'Giữ chỗ và hoàn tất giao dịch'), 'body' => $t('product.guide_step_4_body', 'Bộ phận kinh doanh hướng dẫn quy trình giữ chỗ, đặt cọc, ký hợp đồng và cập nhật tiến độ giao dịch theo từng mốc.')],
    ];
    $usageTermHighlights = array_slice($usageTerms ?? [], 0, 3);
    $usageLocationPreview = array_slice($usageLocationLines ?? [], 0, 3);
    $productionSnapshot = [
        ['label' => $t('product.snapshot_category', 'Phân khúc'), 'value' => $product['tag'] ?? $t('product.snapshot_category_fallback', 'Căn đẹp mở bán')],
        ['label' => $t('product.snapshot_stock', 'Sản phẩm còn hàng'), 'value' => number_format((int) ($product['meta'] ?? 0), 0, ',', '.').' căn'],
        ['label' => $t('product.snapshot_sales', 'Lượt quan tâm'), 'value' => number_format($soldCount, 0, ',', '.').' lead'],
        ['label' => $t('product.snapshot_leadtime', 'Mã căn'), 'value' => $product['sku'] ?? 'LAN0201'],
    ];
    if ($highlights === [] && filled($productModel->short_description)) {
        $highlights = [trim((string) $productModel->short_description)];
    }
    if ($detailParagraphsList === []) {
        $detailParagraphsList = [
            $productModel->short_description ?: $t('product.detail_default_1', 'Sản phẩm này đang dùng mô tả mặc định. Sếp có thể cập nhật nội dung chi tiết ngay trong admin Catalog.'),
            $t('product.detail_default_2', 'Trang chi tiết LAN0201 hỗ trợ gallery nhiều ảnh, thông tin vị trí, chính sách thanh toán và các block nội dung dài cho landing dự án mở bán.'),
        ];
    }
@endphp

@include('theme-lan0201::partials.redesign.product-page', get_defined_vars())
