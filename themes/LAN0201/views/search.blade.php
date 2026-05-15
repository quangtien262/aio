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
    $searchQuery = (string) ($searchQuery ?? request('q', ''));
    $productCollection = collect($products ?? []);
    $searchCategories = collect($searchCategories ?? []);
    $searchFilters = array_merge([
        'q' => $searchQuery,
        'category' => '',
        'sort' => 'default',
        'min_price' => 0,
        'max_price' => 0,
        'available_min_price' => 0,
        'available_max_price' => 0,
    ], $searchFilters ?? []);
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $searchInsightCards = [
        ['value' => number_format((int) ($resultCount ?? $productCollection->count()), 0, ',', '.'), 'label' => $t('search.insight_results', 'mẫu khớp hiện tại')],
        ['value' => number_format($searchCategories->count(), 0, ',', '.'), 'label' => $t('search.insight_categories', 'nhóm đang index')],
        ['value' => $t('search.insight_flow_value', 'Mở bán / tư vấn'), 'label' => $t('search.insight_flow', 'luồng tiếp nhận lead')],
    ];
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
@endphp

@include('theme-lan0201::partials.redesign.search-page', get_defined_vars())
