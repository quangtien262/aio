@php
    $branding = $branding ?? [];
    $topMenu = $topMenu ?? [];
    $productMenu = $productMenu ?? [];
    $cartSummary = $cartSummary ?? ['count' => 0, 'subtotal' => 0];
    $customerAuth = $customerAuth ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $newsletterState ?? ['is_subscribed' => false];
    $presetSwitcher = $presetSwitcher ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $contactHotline = $contactHotline ?? data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = $contactEmail ?? data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = $contactLocation ?? data_get($branding, 'support_location', 'Hồ Chí Minh');
    $activePreset = collect($presetSwitcher['options'] ?? [])->firstWhere('is_active', true);
    $brandSlogan = is_array($activePreset)
        ? (string) ($activePreset['description'] ?? '')
        : '';

    if ($brandSlogan === '') {
        $brandSlogan = data_get($branding, 'slogan', 'Premium booking cho dịch vụ đặt xe, shuttle và điều phối tuyến');
    }

    $isServiceBrand = filled(data_get($branding, 'demo_preset_key')) || str_contains(mb_strtolower((string) $brandSlogan), 'nhà xe');
    $topMenu = collect($topMenu)
        ->map(function (array $item) use ($isServiceBrand): array {
            if (! $isServiceBrand || !empty($item['children'])) {
                return $item;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $url = (string) ($item['url'] ?? '#');

            return match ($label) {
                'Cẩm nang' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Kinh nghiệm đặt xe', 'summary' => 'Checklist, kinh nghiệm và nội dung SEO cho khách đặt tuyến.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Lịch trình tối ưu', 'summary' => 'Gợi ý cách chọn route, loại xe và thời gian khởi hành phù hợp.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                'Giới thiệu' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Về nhà xe', 'summary' => 'Tổng quan thương hiệu, năng lực điều phối và đội xe hiện có.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Quy trình phục vụ', 'summary' => 'Cách tiếp nhận lịch trình, xác nhận chuyến và chăm sóc khách hàng.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                'Báo giá' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Gửi yêu cầu báo giá', 'summary' => 'Điền nhu cầu tuyến, số khách và khung giờ để nhận tư vấn nhanh.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Liên hệ điều phối', 'summary' => 'Xem thông tin liên hệ và đầu mối hỗ trợ cho từng loại nhu cầu.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                default => $item,
            };
        })
        ->values()
        ->all();
@endphp

@if (session('cart_success'))
    <div class="ser-shell-flash">
        <div class="wrap">{{ session('cart_success') }}</div>
    </div>
@endif

<div class="ser-shell-topbar">
    <div class="wrap ser-shell-topbar-inner">
        <div class="ser-shell-inline">
            <span class="ser-shell-status">Concierge 24/7</span>
            <span>{{ $contactLocation }}</span>
            <span>{{ $t('common.hotline_label', 'Hotline') }}: {{ $contactHotline }}</span>
            <span>{{ $t('common.email_label', 'Email') }}: {{ $contactEmail }}</span>
        </div>
        <div class="ser-shell-inline">
            <button type="button" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? $t('common.newsletter_subscribed', 'Đã đăng ký bản tin') : $t('common.newsletter_subscribe', 'Đăng ký bản tin') }}</button>
            @if (!empty($customerAuth['is_authenticated']))
                <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">{{ $t('common.account', 'Tài khoản') }}</a>
            @else
                <button type="button" data-open-auth-modal="register" data-auth-redirect="{{ $postLoginRedirect ?? request()->fullUrl() }}">{{ $t('common.register', 'Đăng ký') }}</button>
                <button type="button" data-open-auth-modal="login" data-auth-redirect="{{ $postLoginRedirect ?? request()->fullUrl() }}">{{ $t('common.login', 'Đăng nhập') }}</button>
            @endif
        </div>
    </div>
</div>

<header class="ser-shell-header">
    <div class="wrap ser-shell-header-inner">
        <a class="ser-shell-brand" href="{{ route('site.home') }}">
            <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'SER0101') }}">
            <span class="ser-shell-brand-copy">
                <strong>{{ data_get($branding, 'company_name', 'SER0101') }}</strong>
                <span>{{ $brandSlogan }}</span>
            </span>
        </a>

        <form class="ser-shell-search" method="GET" action="{{ route('site.catalog.search') }}">
            <input type="search" name="q" placeholder="{{ $t('common.search_placeholder', 'Tìm gói dịch vụ, tuyến đường, loại xe') }}" data-ser-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
            <button type="submit">{{ $t('common.search_button', 'Tìm') }}</button>
        </form>
    </div>

    <nav class="ser-shell-nav">
        <div class="wrap ser-shell-nav-inner">
            <div class="ser-shell-menu ser-shell-menu--editorial">
                @if (!empty($productMenu))
                    <div class="ser-shell-nav-item ser-shell-nav-item--catalog">
                        <a class="ser-shell-nav-link" href="{{ $productMenu[0]['url'] ?? route('site.catalog.search') }}">
                            <span>{{ $t('common.service_menu', 'Nhóm dịch vụ') }}</span>
                        </a>
                        <div class="ser-shell-flyout ser-shell-flyout--catalog">
                            <div class="ser-shell-flyout-grid">
                                @foreach ($productMenu as $menuSection)
                                    @php
                                        $menuSectionLabel = $menuSection['label'] ?? $menuSection['name'] ?? $t('common.service', 'Dịch vụ');
                                        $menuSectionChildren = collect($menuSection['children'] ?? [])->filter(fn ($child): bool => is_array($child))->values();
                                    @endphp
                                    <section class="ser-shell-flyout-card">
                                        <a class="ser-shell-flyout-title" href="{{ $menuSection['url'] ?? '#' }}" target="{{ $menuSection['target'] ?? '_self' }}">
                                            <strong>{{ $menuSectionLabel }}</strong>
                                            <span aria-hidden="true">→</span>
                                        </a>
                                        @if (!empty($menuSection['summary']))
                                            <p>{{ $menuSection['summary'] }}</p>
                                        @endif
                                        @if ($menuSectionChildren->isNotEmpty())
                                            <div class="ser-shell-flyout-list">
                                                @foreach ($menuSectionChildren as $child)
                                                    <a href="{{ $child['url'] ?? '#' }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? $child['name'] ?? $t('common.service', 'Dịch vụ') }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($topMenu as $item)
                    @php
                        $topMenuChildren = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child))->values();
                    @endphp
                    @if ($topMenuChildren->isNotEmpty())
                        <div class="ser-shell-nav-item">
                            <a class="ser-shell-nav-link" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                                <span>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</span>
                            </a>
                            <div class="ser-shell-flyout ser-shell-flyout--primary">
                                <div class="ser-shell-primary-stack">
                                    <a class="ser-shell-primary-feature" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                                        <strong>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</strong>
                                        <span>{{ $item['summary'] ?? 'Xem toàn bộ nội dung trong nhóm điều hướng này.' }}</span>
                                    </a>
                                    <div class="ser-shell-primary-links">
                                        @foreach ($topMenuChildren as $child)
                                            <a href="{{ $child['url'] ?? '#' }}" target="{{ $child['target'] ?? '_self' }}">
                                                <strong>{{ $child['label'] ?? $t('common.menu', 'Menu') }}</strong>
                                                <span>{{ $child['summary'] ?? ($child['description'] ?? 'Đi tới nội dung liên quan trong nhóm này.') }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a class="ser-shell-nav-link ser-shell-nav-link--solo" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                            <span>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            <div class="ser-shell-tools">
                <button type="button" class="ser-shell-cta" data-open-quote-modal>{{ $t('theme.fallback.hero_cta', 'Nhận báo giá') }}</button>
            </div>
        </div>
    </nav>
</header>
