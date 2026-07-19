@php
    $homeData = $themeHomeData ?? [];
    $branding = $homeData['branding'] ?? [];
    $heroBanner = $homeData['hero_banner'] ?? [];
    $sidePromos = $homeData['side_banners'] ?? [];
    $secondarySidePromos = collect($homeData['secondary_side_promos'] ?? [])->take(3)->values()->all();
    $featuredCategories = $homeData['featured_categories'] ?? $homeData['brand_highlights'] ?? [];
    $featuredDeals = $homeData['featured_products'] ?? [];
    $featuredTitle = $homeData['featured_title'] ?? 'Bảng hàng mở bán nổi bật';
    $sections = $homeData['sections'] ?? [];
    $footerColumns = $homeData['footer_columns'] ?? [];
    $cartSummary = $homeData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $homeData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $homeData['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0909 020 201');
    $contactEmail = data_get($branding, 'support_email', 'sales@lan0201.demo');
    $contactLocation = data_get($branding, 'support_location', 'TP.HCM - hành lang mở bán khu Đông');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $workshopMetrics = [
        ['label' => 'Phân khu giới thiệu', 'value' => '06 khu mở bán'],
        ['label' => 'Bảng hàng hiện có', 'value' => '120+ sản phẩm'],
        ['label' => 'Tiện ích nội khu', 'value' => '40+ điểm nhấn'],
        ['label' => 'Lịch hẹn private tour', 'value' => 'Mỗi ngày'],
    ];
    $serviceLanes = [
        [
            'badge' => 'Nhịp mở bán',
            'title' => 'Tổng quan dự án và bảng hàng mở bán được kể như một landing campaign',
            'summary' => 'Khối thông tin được gom lại để kể chuyện dự án, nêu ưu đãi và đưa khách đi nhanh tới các căn nổi bật thay vì bố cục marketplace.',
            'image' => asset('theme-demo/curated/real-estate/projects/project-04.svg'),
        ],
        [
            'badge' => 'Hành trình lead',
            'title' => 'Từ landing đến listing, chi tiết căn và luồng tư vấn trong cùng một visual system',
            'summary' => 'Vẫn dùng chung CatalogProduct nhưng toàn bộ wording, spacing và CTA đã được tái cấu trúc cho hành trình mở bán bất động sản.',
            'image' => asset('theme-demo/curated/real-estate/projects/project-05.svg'),
        ],
    ];
    $heroSlideDefaults = $homeData['hero_slide_defaults'] ?? ['eyebrow' => 'Landing dự án mở bán', 'badge' => 'Nhận brochure và bảng giá mới nhất', 'cta' => 'Khám phá bảng hàng'];
    $heroSlides = collect($homeData['hero_slides'] ?? [])->filter(fn ($slide): bool => is_array($slide) && filled($slide['image'] ?? null));

    if ($heroSlides->isEmpty()) {
        $heroSlides = collect([$heroBanner])
            ->merge(
                collect($sidePromos)->take(3)->map(function (array $promo, int $index) use ($heroSlideDefaults): array {
                    return [
                        'image' => $promo['image'] ?? 'https://picsum.photos/seed/LAN0201-fallback-hero-'.($index + 1).'/960/520',
                        'title' => $promo['title'] ?? 'Phối cảnh dự án mở bán',
                        'summary' => $promo['subtitle'] ?? 'LAN0201 tập trung trình bày thông tin mở bán, bảng hàng và CTA nhận tư vấn cho landing dự án.',
                        'eyebrow' => $heroSlideDefaults['eyebrow'] ?? 'Landing dự án mở bán',
                        'badge' => $heroSlideDefaults['badge'] ?? 'Nhận brochure và bảng giá mới nhất',
                        'cta' => $heroSlideDefaults['cta'] ?? 'Khám phá bảng hàng',
                        'link_url' => $promo['link_url'] ?? '#bang-hang',
                    ];
                })
            )
            ->filter(fn ($slide): bool => is_array($slide) && filled($slide['image'] ?? null));
    }

    if ($footerColumns === []) {
        $footerColumns = [
            ['title' => 'Tổng quan dự án', 'links' => ['Vị trí kết nối vùng', 'Masterplan & phân khu', 'Timeline mở bán', 'Tiến độ xây dựng']],
            ['title' => 'Bảng hàng & chính sách', 'links' => ['Giỏ hàng căn đẹp', 'Ưu đãi theo giai đoạn', 'Phương án thanh toán', 'FAQ dành cho nhà đầu tư']],
            ['title' => 'Kết nối sales gallery', 'links' => ['Đặt lịch xem nhà mẫu', 'Nhận brochure PDF', 'Liên hệ phòng kinh doanh', 'Chính sách bảo mật lead']],
        ];
    }
@endphp

@if (isset($landingBlocks) && is_array($landingPage ?? null))
    @include('partials.configurable-landing-document')
@else
    @include('theme-lan0201::partials.redesign.home-page', get_defined_vars())
@endif
