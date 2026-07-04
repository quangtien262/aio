@php
    $themeShellData = $themeShellData ?? [];
    $branding = $themeShellData['branding'] ?? [];
    $topMenu = $themeShellData['top_menu'] ?? [];
    $cartSummary = $themeShellData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $themeShellData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $themeShellData['newsletter'] ?? ['is_subscribed' => false];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('LAN0201', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0909 020 201');
    $contactEmail = data_get($branding, 'support_email', 'sales@lan0201.demo');
    $contactLocation = data_get($branding, 'support_location', 'TP.HCM - hành lang mở bán khu Đông');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $pageSlug = (string) ($entry->slug ?? '');
    $isAboutPage = ($contentType ?? null) === 'page' && in_array($pageSlug, ['gioi-thieu', 'about'], true);
    $isContactPage = ($contentType ?? null) === 'contact' || (($contentType ?? null) === 'page' && in_array($pageSlug, ['lien-he', 'contact'], true));
    $isPostDetail = ($contentType ?? null) === 'post';
    $isServiceListing = ($contentType ?? null) === 'services';
    $isServiceDetail = ($contentType ?? null) === 'service';
    $listingCollection = isset($listingItems) ? collect($listingItems->items()) : collect();
    $postFilters = $postFilters ?? ['q' => '', 'category' => ''];
    $postCategories = collect($postCategories ?? []);
    $latestPostItems = collect($latestPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
    $relatedPostItems = collect($relatedPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
    $footerColumns = [
        $t('footer.help_title', 'Thông tin dự án') => [$t('footer.shipping_policy', 'Tổng quan vị trí'), $t('footer.payment_methods', 'Hướng dẫn đặt lịch'), $t('footer.evouchers', 'Bảng giá và ưu đãi'), $t('footer.membership', 'Chính sách thanh toán')],
        $t('footer.about_title', 'Hỗ trợ giao dịch') => [$t('footer.about_us', 'Mặt bằng tổng thể'), $t('footer.contact', 'Liên hệ phòng kinh doanh'), $t('footer.privacy_policy', 'Cam kết pháp lý'), $t('footer.operating_regulations', 'Câu hỏi thường gặp')],
        $t('footer.partnership_title', 'Kết nối tư vấn') => [$t('footer.gift_cards', 'Đăng ký nhận tư vấn'), $t('footer.partner_contact', 'Tải brochure dự án'), $t('footer.careers', 'Nhận thông tin mở bán'), $t('footer.press_info', 'Chính sách bảo mật')],
    ];
@endphp

@include('theme-lan0201::partials.redesign.cms-page', get_defined_vars())
