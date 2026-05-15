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
    $activeFilters = $filters ?? [];
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $productCollection = collect($products ?? []);
    $minPrice = (int) ($activeFilters['available_min_price'] ?? 0);
    $maxPrice = (int) ($activeFilters['available_max_price'] ?? 0);
    $selectedMinPrice = (int) ($activeFilters['selected_min_price'] ?? $minPrice);
    $selectedMaxPrice = (int) ($activeFilters['selected_max_price'] ?? $maxPrice);
    $selectedSort = (string) ($activeFilters['sort'] ?? 'default');
    $queryForUrl = function (array $overrides = []) use ($selectedSort, $selectedMinPrice, $selectedMaxPrice, $minPrice, $maxPrice): array {
        $query = ['sort' => $selectedSort, 'min_price' => $selectedMinPrice, 'max_price' => $selectedMaxPrice];
        foreach ($overrides as $key => $value) {
            $query[$key] = $value;
        }
        if (($query['sort'] ?? 'default') === 'default') unset($query['sort']);
        if (($query['min_price'] ?? $minPrice) <= $minPrice) unset($query['min_price']);
        if (($query['max_price'] ?? $maxPrice) >= $maxPrice) unset($query['max_price']);
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
        $rootCategoryUrl = route('site.catalog.category', ['slug' => $category->slug]);
        $query = $queryForUrl([]);
        $categoryLinks = collect([['label' => $category->name, 'url' => $query === [] ? $rootCategoryUrl : $rootCategoryUrl.'?'.http_build_query($query), 'count' => $productCollection->count(), 'active' => true]]);
    }
    $sortOptions = [
        ['label' => $t('search.sort_default', 'Mặc định'), 'value' => 'default'],
        ['label' => $t('search.sort_bestseller', 'Bán chạy'), 'value' => 'bestseller'],
        ['label' => $t('search.sort_price_asc', 'Giá thấp nhất'), 'value' => 'price_asc'],
        ['label' => $t('search.sort_price_desc', 'Giá cao nhất'), 'value' => 'price_desc'],
        ['label' => $t('search.sort_newest', 'Mới nhất'), 'value' => 'newest'],
    ];
    $categoryIntro = filled($category->description)
        ? $category->description
        : $t('category.atelier_intro', 'Danh sách này được dùng để trình bày bảng hàng mở bán theo phân khu, loại hình và mức giá, giúp đội sales chốt nhu cầu nhanh hơn.');
    $footerColumns = [
        $t('footer.help_title', 'Thông tin dự án') => [$t('footer.shipping_policy', 'Tổng quan vị trí'), $t('footer.payment_methods', 'Hướng dẫn đặt lịch'), $t('footer.evouchers', 'Bảng giá và ưu đãi'), $t('footer.membership', 'Chính sách thanh toán')],
        $t('footer.about_title', 'Hỗ trợ giao dịch') => [$t('footer.about_us', 'Mặt bằng tổng thể'), $t('footer.contact', 'Liên hệ phòng kinh doanh'), $t('footer.privacy_policy', 'Cam kết pháp lý'), $t('footer.operating_regulations', 'Câu hỏi thường gặp')],
        $t('footer.partnership_title', 'Kết nối tư vấn') => [$t('footer.gift_cards', 'Đăng ký nhận tư vấn'), $t('footer.partner_contact', 'Tải brochure dự án'), $t('footer.careers', 'Nhận thông tin mở bán'), $t('footer.press_info', 'Chính sách bảo mật')],
    ];
@endphp

@include('theme-lan0201::partials.redesign.category-page', get_defined_vars())
