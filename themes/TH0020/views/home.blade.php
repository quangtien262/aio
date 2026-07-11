@php
    $homeData = $themeHomeData ?? [];
    $siteProfile = $homeData['site_profile'] ?? $siteProfile ?? \App\Models\SiteProfile::query()->first();
    $branding = $homeData['branding'] ?? [];
    $heroBanner = $homeData['hero_banner'] ?? [];
    $sidePromos = $homeData['side_banners'] ?? [];
    $secondarySidePromos = collect($homeData['secondary_side_promos'] ?? [])->take(3)->values()->all();
    $featuredCategories = $homeData['featured_categories'] ?? $homeData['brand_highlights'] ?? [];
    $sidebarCategories = $homeData['product_menu'] ?? [];
    $InteriorCategoryTiles = $homeData['interior_category_tiles'] ?? $homeData['fashion_category_tiles'] ?? [];
    $featuredDeals = $homeData['featured_products'] ?? [];
    $featuredTitle = $homeData['featured_title'] ?? 'Sản phẩm nổi bật';
    $sections = $homeData['sections'] ?? [];
    $latestPostsSection = $homeData['latest_posts_section'] ?? [];
    $latestPosts = $homeData['latest_posts'] ?? [];
    $heroSlideDefaults = $homeData['hero_slide_defaults'] ?? ['eyebrow' => 'Interior studio', 'badge' => 'New living', 'cta' => 'Xem bộ sưu tập'];
    $footerColumns = $homeData['footer_columns'] ?? [];
    $companyFooter = $homeData['company_footer'] ?? [];
    $cartSummary = $homeData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $homeData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $homeData['newsletter'] ?? ['is_subscribed' => false];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $themeBlockRegistry = app(\App\Support\ThemeBlockRegistry::class);
    $themeKey = 'TH0020';
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('TH0020', app()->getLocale(), $key, $default);
    $latestPostsSection = array_merge([
        'kicker' => $t('home.latest_posts_kicker', 'Interior journal'),
        'title' => $t('home.latest_posts_title', 'Ý tưởng mới cho không gian sống'),
        'summary' => $t('home.latest_posts_summary', 'Cập nhật xu hướng nội thất, cách phối vật liệu và gợi ý chọn sản phẩm từ CMS.'),
    ], $latestPostsSection);
    $adminUser = auth('admin')->user();
    $canQuickEditThemeBlocks = $adminUser !== null
        && $adminUser->hasPermission('theme.view')
        && $adminUser->hasPermission('theme.customize');
    $quickEditLocales = \App\Support\FrontendLocalization::supportedLocales();
    $quickEditLocaleOptions = \App\Support\FrontendLocalization::localeOptions();
    $heroBannerEditKeyMap = collect($heroBanner['edit_fields'] ?? [])->keyBy('slot')->all();
    $managedHeroSlides = collect($homeData['hero_slides'] ?? [])->filter(fn ($slide): bool => is_array($slide) && filled($slide['image'] ?? null))->values();
    $heroQuickEditFields = $managedHeroSlides->isNotEmpty()
        ? $managedHeroSlides->flatMap(fn (array $slide): array => $slide['edit_fields'] ?? [])->values()->all()
        : array_merge(
            $heroBanner['edit_fields'] ?? [],
            [
                ['key' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.eyebrow'), 'label' => 'Nhãn slide ph?', 'group' => 'content', 'entity' => 'theme'],
                ['key' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.badge'), 'label' => 'Badge slide ph?', 'group' => 'content', 'entity' => 'theme'],
                ['key' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.cta'), 'label' => 'CTA slide ph?', 'group' => 'content', 'entity' => 'theme'],
            ],
        );
    $heroSlideDefaultKeyMap = [
        'eyebrow' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.eyebrow'),
        'badge' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.badge'),
        'cta' => $themeBlockRegistry->contentKey($themeKey, 'hero_slide.cta'),
    ];
    $companyFooterEditFields = [
        ['key' => $themeBlockRegistry->contentKey($themeKey, 'company_footer.address_line_1'), 'label' => 'Ð?a ch? dòng 1', 'group' => 'content', 'entity' => 'theme'],
        ['key' => $themeBlockRegistry->contentKey($themeKey, 'company_footer.address_line_2'), 'label' => 'Ð?a ch? dòng 2', 'group' => 'content', 'entity' => 'theme'],
    ];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0902.020.020');
    $contactEmail = data_get($branding, 'support_email', 'studio@th0020.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội - TP.HCM');
    $companyTitle = data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', ''));
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $searchCategories = collect($sidebarCategories)->pluck('label')->take(6)->all();
    $heroSlides = $managedHeroSlides->isNotEmpty()
        ? $managedHeroSlides
        : collect([$heroBanner])
            ->merge(
                collect($sidePromos)->take(3)->map(function (array $promo, int $index) use ($heroSlideDefaults): array {
                    return [
                        'image' => $promo['image'] ?? 'https://picsum.photos/seed/TH0020-fallback-hero-'.($index + 1).'/960/520',
                        'title' => $promo['title'] ?? 'Không gian mới cho căn hộ hiện đại',
                        'summary' => $promo['subtitle'] ?? 'Khám phá các bộ sưu tập sofa, bàn ăn, giường ngủ và decor đang nổi bật.',
                        'eyebrow' => $heroSlideDefaults['eyebrow'] ?? 'Interior studio',
                        'badge' => $heroSlideDefaults['badge'] ?? 'Khám phá ngay',
                        'cta' => $heroSlideDefaults['cta'] ?? 'Xem ngay',
                        'link_url' => $promo['link_url'] ?? route('site.catalog.search'),
                    ];
                })
            )
            ->filter(fn ($slide): bool => is_array($slide) && filled($slide['image'] ?? null))
            ->values();
    $InteriorBannerSlides = collect($homeData['interior_banner_slides'] ?? $homeData['fashion_banner_slides'] ?? []);
    $InteriorBannerSlides = $InteriorBannerSlides->isNotEmpty()
        ? $InteriorBannerSlides->take(5)->values()
        : ($heroSlides->isNotEmpty()
            ? $heroSlides->take(5)->values()
            : collect([[
                'image' => data_get($heroBanner, 'image', 'https://picsum.photos/seed/TH0020-banner/1440/520'),
                'link_url' => route('site.catalog.search'),
                'title' => $companyTitle ?: 'TH0020 Interior',
            ]]));
    $topSidePromos = collect($sidePromos)->take(2)->values();
    if ($topSidePromos->isEmpty()) {
        $topSidePromos = collect([
            [
                'title' => 'Phòng khách tinh gọn',
                'subtitle' => 'Sofa, bàn trà và kệ TV theo cùng tông vật liệu.',
                'image' => 'https://picsum.photos/seed/th0020-side-living/720/520',
                'link_url' => route('site.catalog.search'),
                'target' => '_self',
            ],
            [
                'title' => 'Góc ăn ấm sáng',
                'subtitle' => 'Bàn ăn, ghế gỗ và đèn treo cho căn hộ đô thị.',
                'image' => 'https://picsum.photos/seed/th0020-side-dining/720/520',
                'link_url' => route('site.catalog.search'),
                'target' => '_self',
            ],
        ]);
    }

    $topMenuItems = collect($homeData['top_menu'] ?? [])->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? null))->values()->all();
    if ($topMenuItems === []) {
        $topMenuItems = [
            ['label' => $t('home.menu.new_arrivals', 'Hàng mới'), 'url' => route('site.catalog.search'), 'target' => '_self', 'children' => [
                ['label' => $t('home.menu.this_week', 'Mới tuần này'), 'url' => route('site.catalog.search')],
                ['label' => $t('home.menu.best_fit', 'Dễ phối không gian'), 'url' => route('site.catalog.search')],
            ]],
            ['label' => $t('home.menu.collections', 'Bộ sưu tập'), 'url' => route('site.catalog.search'), 'target' => '_self', 'children' => [
                ['label' => $t('home.menu.office_capsule', 'Căn hộ tối giản'), 'url' => route('site.catalog.search')],
                ['label' => $t('home.menu.weekend_edit', 'Nhà phố ấm sáng'), 'url' => route('site.catalog.search')],
            ]],
            ['label' => $t('home.menu.lookbook', 'Cảm hứng'), 'url' => route('site.blog.index'), 'target' => '_self', 'children' => [
                ['label' => $t('home.menu.style_notes', 'Gợi ý phối phòng'), 'url' => route('site.blog.index')],
                ['label' => $t('home.menu.care_guide', 'Chăm sóc vật liệu'), 'url' => route('site.blog.index')],
            ]],
            ['label' => $t('home.menu.sale', 'Sale'), 'url' => route('site.catalog.search'), 'target' => '_self', 'children' => [
                ['label' => $t('home.menu.price_drop', 'Giá tốt'), 'url' => route('site.catalog.search')],
                ['label' => $t('home.menu.last_size', 'Hàng trưng bày'), 'url' => route('site.catalog.search')],
            ]],
        ];
    }
    $topMenuItems = collect($topMenuItems)->values()->all();

    if ($sidebarCategories === []) {
        $sidebarCategories = [
            ['label' => $t('home.demo.category.women', 'Phòng khách'), 'url' => route('site.catalog.search'), 'target' => '_self', 'icon' => '▣', 'highlight' => true, 'children' => [
                ['label' => $t('home.demo.category.maxi', 'Sofa & armchair'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.office', 'Bàn trà'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.party', 'Kệ TV'), 'url' => route('site.catalog.search'), 'target' => '_self'],
            ]],
            ['label' => $t('home.demo.category.tops', 'Phòng ngủ'), 'url' => route('site.catalog.search'), 'target' => '_self', 'icon' => '▥', 'highlight' => false, 'children' => [
                ['label' => $t('home.demo.category.shirt', 'Giường ngủ'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.crop', 'Tủ đầu giường'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.knit', 'Tủ áo'), 'url' => route('site.catalog.search'), 'target' => '_self'],
            ]],
            ['label' => $t('home.demo.category.bottoms', 'Bàn ăn & bếp'), 'url' => route('site.catalog.search'), 'target' => '_self', 'icon' => '▤', 'highlight' => false, 'children' => [
                ['label' => $t('home.demo.category.trouser', 'Bàn ăn'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.skirt', 'Ghế ăn'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.denim', 'Tủ bếp phụ'), 'url' => route('site.catalog.search'), 'target' => '_self'],
            ]],
            ['label' => $t('home.demo.category.accessories', 'Decor & đèn'), 'url' => route('site.catalog.search'), 'target' => '_self', 'icon' => '◇', 'highlight' => false, 'children' => [
                ['label' => $t('home.demo.category.bag', 'Đèn trang trí'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.belt', 'Thảm'), 'url' => route('site.catalog.search'), 'target' => '_self'],
                ['label' => $t('home.demo.category.scarf', 'Bình & tranh'), 'url' => route('site.catalog.search'), 'target' => '_self'],
            ]],
        ];
    }

    if ($InteriorCategoryTiles === []) {
        $InteriorCategoryTiles = $sidebarCategories;
    }

    if ($featuredCategories === []) {
        $featuredCategories = collect($sidebarCategories)->take(5)->values()->map(fn (array $category, int $index): array => [
            'name' => $category['label'] ?? $t('common.category', 'Danh m?c'),
            'url' => $category['url'] ?? route('site.catalog.search'),
            'target' => $category['target'] ?? '_self',
            'tone' => ['#b20f3a', '#1f1a1d', '#0f8a8a', '#c17a3a', '#6b4b7d'][$index % 5],
        ])->all();
    }

    $demoProducts = collect([
        ['title' => $t('home.demo.product.linen_blazer', 'Sofa module vải bouclé'), 'image' => 'https://picsum.photos/seed/TH0020-boucle-sofa/900/720', 'price' => 12800000, 'old_price' => 14900000, 'discount' => 14, 'tag' => $t('home.demo.tag.new', 'New living'), 'meta' => 18],
        ['title' => $t('home.demo.product.silk_shirt', 'Bàn trà gỗ sồi bo cạnh'), 'image' => 'https://picsum.photos/seed/TH0020-oak-coffee-table/900/720', 'price' => 4200000, 'old_price' => 5200000, 'discount' => 19, 'tag' => $t('home.demo.tag.workwear', 'Oak finish'), 'meta' => 32],
        ['title' => $t('home.demo.product.midi_skirt', 'Ghế ăn lưng cong bọc nỉ'), 'image' => 'https://picsum.photos/seed/TH0020-dining-chair/900/720', 'price' => 1850000, 'old_price' => 2400000, 'discount' => 23, 'tag' => $t('home.demo.tag.editor_pick', 'Editor pick'), 'meta' => 21],
        ['title' => $t('home.demo.product.knit_top', 'Đèn sàn kim loại mờ'), 'image' => 'https://picsum.photos/seed/TH0020-floor-lamp/900/720', 'price' => 2600000, 'old_price' => 3200000, 'discount' => 19, 'tag' => $t('home.demo.tag.easy_mix', 'Warm light'), 'meta' => 44],
        ['title' => $t('home.demo.product.maxi_dress', 'Giường platform gỗ veneer'), 'image' => 'https://picsum.photos/seed/TH0020-platform-bed/900/720', 'price' => 15800000, 'old_price' => 17900000, 'discount' => 12, 'tag' => $t('home.demo.tag.resort', 'Bedroom'), 'meta' => 16],
        ['title' => $t('home.demo.product.wide_pants', 'Tủ console cửa mây'), 'image' => 'https://picsum.photos/seed/TH0020-rattan-console/900/720', 'price' => 7200000, 'old_price' => 8900000, 'discount' => 19, 'tag' => $t('home.demo.tag.city', 'Rattan'), 'meta' => 27],
        ['title' => $t('home.demo.product.mini_bag', 'Thảm len dệt tay màu cát'), 'image' => 'https://picsum.photos/seed/TH0020-wool-rug/900/720', 'price' => 3900000, 'old_price' => 4800000, 'discount' => 19, 'tag' => $t('home.demo.tag.accessory', 'Decor'), 'meta' => 12],
        ['title' => $t('home.demo.product.denim_jacket', 'Kệ sách module thanh mảnh'), 'image' => 'https://picsum.photos/seed/TH0020-bookshelf/900/720', 'price' => 6400000, 'old_price' => 7800000, 'discount' => 18, 'tag' => $t('home.demo.tag.street', 'Storage'), 'meta' => 19],
    ])->map(fn (array $item, int $index): array => array_merge($item, ['url' => route('site.catalog.search'), 'sku' => 'TH0020-DEMO-'.($index + 1)]))->values();

    if ($featuredDeals === []) {
        $featuredDeals = $demoProducts->all();
        $featuredTitle = $t('theme.fallback.featured_products', 'S?n ph?m n?i b?t');
    }

    if ($sections === []) {
        $sections = [
            [
                'theme' => 'pink',
                'title' => $t('home.demo.section.office_title', 'Office capsule'),
                'slug' => 'office-capsule',
                'url' => route('site.catalog.search'),
                'tabs' => [$t('home.demo.tab.new', 'M?i v?'), $t('home.demo.tab.best', 'Bán ch?y'), $t('home.demo.tab.mix', 'D? ph?i')],
                'filters' => [$t('home.demo.filter.blazer', 'Blazer'), $t('home.demo.filter.shirt', 'So mi'), $t('home.demo.filter.trouser', 'Qu?n âu')],
                'items' => $demoProducts->slice(0, 4)->values()->all(),
            ],
            [
                'theme' => 'lime',
                'title' => $t('home.demo.section.weekend_title', 'Weekend edit'),
                'slug' => 'weekend-edit',
                'url' => route('site.catalog.search'),
                'tabs' => [$t('home.demo.tab.lookbook', 'Lookbook'), $t('home.demo.tab.resort', 'Resort'), $t('home.demo.tab.casual', 'Casual')],
                'filters' => [$t('home.demo.filter.dress', 'Ð?m'), $t('home.demo.filter.denim', 'Denim'), $t('home.demo.filter.bag', 'Túi')],
                'items' => $demoProducts->slice(4, 4)->values()->all(),
            ],
        ];
    }

    if ($latestPosts === []) {
        $latestPosts = [
            ['title' => $t('home.demo.post.size_title', 'Cách ch?n size khi mua online'), 'excerpt' => $t('home.demo.post.size_excerpt', 'G?i ý d?c s? do, form dáng và ch?t li?u d? gi?m d?i tr? cho shop th?i trang.'), 'url' => route('site.blog.index'), 'image' => 'https://picsum.photos/seed/TH0020-post-size/900/700', 'published_at' => now()->subDays(2)->format('d/m/Y')],
            ['title' => $t('home.demo.post.lookbook_title', 'Lookbook tu?n này: linen và denim'), 'excerpt' => $t('home.demo.post.lookbook_excerpt', 'M?t layout nh? cho shop c?p nh?t c?m h?ng ph?i d? và d?n khách v? danh m?c s?n ph?m.'), 'url' => route('site.blog.index'), 'image' => 'https://picsum.photos/seed/TH0020-post-lookbook/900/700', 'published_at' => now()->subDays(5)->format('d/m/Y')],
            ['title' => $t('home.demo.post.care_title', 'B?o qu?n d? l?a và knitwear'), 'excerpt' => $t('home.demo.post.care_excerpt', 'N?i dung h?u mãi giúp tang ni?m tin và t?o thêm di?m ch?m sau mua.'), 'url' => route('site.blog.index'), 'image' => 'https://picsum.photos/seed/TH0020-post-care/900/700', 'published_at' => now()->subDays(8)->format('d/m/Y')],
        ];
    }

    if ($footerColumns === []) {
        $footerColumns = [
            ['title' => $t('footer.help_title', 'Tr? giúp'), 'links' => [
                ['label' => $t('footer.shipping_policy', 'Chính sách giao hàng'), 'url' => url('/'.app()->getLocale().'/chinh-sach-giao-hang')],
                ['label' => $t('footer.payment_methods', 'Cách th?c thanh toán'), 'url' => url('/'.app()->getLocale().'/cach-thuc-thanh-toan')],
                ['label' => $t('footer.evouchers', 'Interior E-voucher'), 'url' => route('site.catalog.search')],
                ['label' => $t('footer.membership', 'Membership'), 'url' => route('customer.account')],
            ]],
            ['title' => $t('footer.about_title', 'Gi?i thi?u'), 'links' => [
                ['label' => $t('footer.about_us', 'V? chúng tôi'), 'url' => url('/'.app()->getLocale().'/gioi-thieu')],
                ['label' => $t('footer.contact', 'Liên h?'), 'url' => route('site.contact')],
                ['label' => $t('footer.privacy_policy', 'Chính sách b?o m?t'), 'url' => url('/'.app()->getLocale().'/chinh-sach-bao-mat')],
                ['label' => $t('footer.operating_regulations', 'Quy ch? ho?t d?ng'), 'url' => url('/'.app()->getLocale().'/quy-che-hoat-dong')],
            ]],
            ['title' => $t('footer.partnership_title', 'H?p tác'), 'links' => [
                ['label' => $t('footer.gift_cards', 'Th? quà t?ng'), 'url' => route('site.catalog.search')],
                ['label' => $t('footer.partner_contact', 'Liên h? h?p tác'), 'url' => route('site.contact')],
                ['label' => $t('footer.careers', 'Tuy?n d?ng'), 'url' => url('/'.app()->getLocale().'/tuyen-dung')],
                ['label' => $t('footer.press_info', 'Thông tin báo chí'), 'url' => route('site.blog.index')],
            ]],
        ];
    }

    $footerColumnEditFields = collect($footerColumns)->values()->map(function (array $column, int $index) use ($themeBlockRegistry, $themeKey): array {
        return [
            'title' => $column['title'] ?? sprintf('C?t %d', $index + 1),
            'fields' => array_merge(
                [[
                    'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('footer.columns.%d.title', $index)),
                    'label' => 'Tiêu d? c?t',
                    'group' => 'content',
                    'entity' => 'theme',
                ]],
                collect($column['links'] ?? [])->values()->map(fn (string|array $link, int $linkIndex): array => [
                    'key' => $themeBlockRegistry->contentKey($themeKey, sprintf('footer.columns.%d.links.%d', $index, $linkIndex)),
                    'label' => sprintf('Link %d', $linkIndex + 1),
                    'group' => 'content',
                    'entity' => 'theme',
                ])->all(),
            ),
        ];
    })->all();

    $formatCurrency = fn ($value) => $value === null ? 'Liên h?' : number_format((float) $value, 0, ',', '.').'d';
    $formatDiscount = fn ($value) => '-'.(int) $value.'%';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $companyTitle }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-th0020::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .th-page { min-height: 100vh; }
            .th-topbar { background: #ffffff; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
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
            .th-main-nav { position: relative; background: var(--th-red); color: #fff; z-index: 30; }
            .th-main-nav-inner { min-height: 42px; justify-content: flex-start; }
            .th-home-link { width: 48px; min-height: 42px; display: grid; place-items: center; background: rgba(0,0,0,.12); font-size: 17px; transition: background .18s ease, color .18s ease; }
            .th-home-link:hover { background: rgba(255,255,255,.12); color: #fff2bf; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 0; font-size: 14px; font-weight: 700; }
            .th-nav-item { position: relative; }
            .th-nav-link { min-height: 42px; padding: 0 18px; display: inline-flex; align-items: center; gap: 8px; text-align: left; text-transform: uppercase; letter-spacing: .08em; transition: color .18s ease, background .18s ease; cursor: pointer; }
            .th-nav-link:hover, .th-nav-item:hover > .th-nav-link { color: #fff2bf; background: rgba(255,255,255,.06); }
            .th-nav-caret { font-size: 11px; opacity: .72; transform: translateY(-1px); }
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 170px; padding: 11px 14px; font-weight: 700; }
            .th-nav-products { position: static; background: #b20f3a; }
            .th-nav-products-panel { position: absolute; top: 100%; left: 0; right: 0; background: #fffaf6; color: #1f1a1d; border-bottom: 1px solid #eadfda; box-shadow: 0 26px 60px rgba(31,26,29,.16); opacity: 0; visibility: hidden; transform: translateY(12px); pointer-events: none; transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-nav-item:hover .th-nav-products-panel, .th-nav-item:focus-within .th-nav-products-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-products-inner { width: min(1200px, calc(100% - 24px)); margin: 0 auto; padding: 26px 0 30px; display: grid; grid-template-columns: minmax(230px, .28fr) minmax(0, 1fr); gap: 26px; }
            .th-nav-products-intro { background: #1f1a1d; color: #fff; padding: 24px; display: grid; gap: 12px; align-content: start; }
            .th-nav-products-intro span { color: #f2c94c; font-size: 12px; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
            .th-nav-products-intro strong { font-family: Georgia, 'Times New Roman', serif; font-size: 34px; line-height: 1.05; font-weight: 500; }
            .th-nav-products-intro small { color: rgba(255,255,255,.72); line-height: 1.65; }
            .th-nav-products-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .th-nav-category-card { min-width: 0; display: grid; gap: 12px; align-content: start; padding: 18px; background: #fff; border: 1px solid #eadfda; transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease; }
            .th-nav-category-card:hover { transform: translateY(-3px); border-color: #1f1a1d; box-shadow: 0 18px 40px rgba(31,26,29,.1); }
            .th-nav-category-head { display: flex; align-items: center; gap: 10px; color: #1f1a1d; }
            .th-nav-category-icon { width: 34px; height: 34px; display: grid; place-items: center; background: #f7f0eb; color: #b20f3a; font-size: 16px; }
            .th-nav-category-head strong { font-size: 15px; line-height: 1.25; text-transform: uppercase; letter-spacing: .06em; }
            .th-nav-category-links { display: grid; gap: 8px; }
            .th-nav-category-links a { color: #756a70; font-size: 13px; line-height: 1.45; }
            .th-nav-category-links a:hover { color: #b20f3a; }
            .th-nav-simple-panel { position: absolute; top: 100%; left: 0; min-width: 220px; background: #fff; color: #1f1a1d; border: 1px solid #eadfda; box-shadow: 0 18px 42px rgba(31,26,29,.14); padding: 8px; opacity: 0; visibility: hidden; transform: translateY(10px); pointer-events: none; transition: opacity .18s ease, transform .22s ease, visibility .22s ease; }
            .th-nav-item:hover .th-nav-simple-panel, .th-nav-item:focus-within .th-nav-simple-panel { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .th-nav-simple-panel a { display: block; padding: 10px 12px; color: #4f454a; font-size: 13px; line-height: 1.35; }
            .th-nav-simple-panel a:hover { background: #fff4ee; color: #b20f3a; }
            .th-content { padding: 0 0 40px; }
            .th-hero-layout { display: grid; grid-template-columns: 220px 1fr; gap: 16px; margin-top: 0; }
            .th-sidebar { position: relative; background: var(--th-surface); border: 1px solid var(--th-line); z-index: 5; }
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
            .th-hero-stack { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 12px; }
            .th-hero-card { background: linear-gradient(90deg, #fff3ea 0%, #fff 100%); min-height: 300px; position: relative; overflow: hidden; border: 1px solid #ffd7bd; }
            .th-hero-slide { position: absolute; inset: 0; opacity: 0; pointer-events: none; transition: opacity .6s ease; }
            .th-hero-slide.is-active { opacity: 1; pointer-events: auto; z-index: 1; }
            .th-hero-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .th-hero-overlay {position: relative;z-index: 1;width: min(54%, 420px);padding: 36px 32px;background: linear-gradient(90deg, rgb(0 0 0 / 95%) 0%, rgb(255 255 255 / 14%) 100%);height: 100%;}
            .th-eyebrow { display: inline-flex; padding: 6px 12px; border-radius: 999px; background: rgba(239,43,45,0.1); color: #ff6668; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
            .th-hero-title { margin: 14px 0 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.05; color: #ffffff; }
            .th-hero-summary { margin: 0 0 20px; color: #ffffff; line-height: 1.6; }
            .th-hero-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .th-badge-price { background: #fff; color: var(--th-red); border-radius: 20px; padding: 10px 14px; font-size: 15px; font-weight: 800; box-shadow: 0 10px 24px rgba(239,43,45,0.14); }
            .th-hero-button { background: linear-gradient(180deg, #ff8e18 0%, #f25c05 100%); color: #fff; border-radius: 999px; padding: 11px 22px; font-weight: 800; text-transform: uppercase; }
            .th-hero-nav { position: absolute; top: 50%; z-index: 3; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; margin-top: -22px; padding: 0; border: 0; border-radius: 999px; background: rgba(255,255,255,.88); color: #303030; font-size: 28px; line-height: 1; text-align: center; box-shadow: 0 12px 24px rgba(19, 21, 33, 0.16); cursor: pointer; opacity: 0; visibility: hidden; transform: translateY(-50%) scale(.92); transition: opacity .2s ease, visibility .2s ease, transform .2s ease, background .18s ease; }
            .th-hero-card:hover .th-hero-nav, .th-hero-card:focus-within .th-hero-nav { opacity: 1; visibility: visible; transform: translateY(-50%) scale(1); }
            .th-hero-nav:hover { background: #fff; transform: translateY(-50%) scale(1.06); }
            .th-hero-nav-prev { left: 5px; }
            .th-hero-nav-next { right: 5px; }
            .th-hero-dots { position: absolute; left: 32px; bottom: 20px; z-index: 3; display: flex; align-items: center; gap: 8px; }
            .th-hero-dot { width: 10px; height: 10px; border: 0; border-radius: 999px; background: rgba(255,255,255,.55); cursor: pointer; transition: transform .18s ease, background .18s ease; }
            .th-hero-dot.is-active { background: #fff; transform: scale(1.25); }
            .th-side-promo-grid { display: grid; gap: 8px; }
            .th-side-promo { min-height: 69px; position: relative; overflow: hidden; border: 1px solid var(--th-line); }
            .th-side-promo img { width: 100%; height: 100%; object-fit: cover; }
            .th-side-promo span { position: absolute; left: 12px; bottom: 10px; z-index: 1; color: #fff; font-size: 13px; font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,0.45); }
            .th-brand-strip { margin-top: 12px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 16px 20px; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
            .th-brand { display: flex; align-items: center; justify-content: center; text-align: center; }
            .th-brand-badge { width: 92px; height: 92px; padding: 12px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-weight: 900; font-size: 12px; line-height: 1.2; text-align: center; text-wrap: balance; overflow-wrap: anywhere; word-break: normal; hyphens: auto; box-shadow: var(--th-shadow); }
            .th-section-tabs { display: flex; gap: 24px; font-size: 14px; color: #7d7d7d; text-transform: uppercase; }
            .th-section-tabs span:first-child { color: var(--th-ink); font-weight: 800; }
            .th-featured-panel { margin-top: 22px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 0 0 22px; }
            .th-featured-topbar { padding: 0 16px; display: flex; align-items: center; gap: 24px; min-height: 48px; border-bottom: 1px solid var(--th-line); }
            .th-card-grid { padding: 18px 16px 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .th-secondary-promo-section { margin-top: 18px; background: var(--th-surface); border: 1px solid var(--th-line); padding: 16px; }
            .th-secondary-promo-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
            .th-secondary-promo-head h3 { margin: 0; font-size: 22px; text-transform: uppercase; color: var(--th-ink); }
            .th-secondary-promo-head p { margin: 4px 0 0; color: var(--th-muted); font-size: 13px; }
            .th-secondary-promo-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); grid-template-rows: repeat(2, minmax(0, 180px)); gap: 16px; }
            .th-secondary-promo-card { position: relative; min-height: 180px; overflow: hidden; border: 1px solid var(--th-line); background: #111; }
            .th-secondary-promo-card:first-child { grid-row: 1 / span 2; min-height: 376px; }
            .th-secondary-promo-card:first-child .th-secondary-promo-copy strong { font-size: 30px; }
            .th-secondary-promo-card:first-child .th-secondary-promo-copy > span:not(.th-secondary-promo-cta) { font-size: 14px; max-width: 75%; }
            .th-secondary-promo-card img { width: 100%; height: 100%; object-fit: cover; }
            .th-secondary-promo-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(6, 8, 14, 0.08) 0%, rgba(6, 8, 14, 0.84) 100%); }
            .th-secondary-promo-badge { position: absolute; top: 16px; left: 16px; z-index: 1; display: inline-flex; align-items: center; padding: 6px 11px; border-radius: 999px; background: rgba(255,255,255,0.16); color: #fff; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; backdrop-filter: blur(6px); }
            .th-secondary-promo-copy { position: absolute; inset: auto 16px 16px 16px; z-index: 1; color: #fff; }
            .th-secondary-promo-copy strong { display: block; font-size: 22px; line-height: 1.15; }
            .th-secondary-promo-copy > span:not(.th-secondary-promo-cta) { display: block; margin-top: 8px; color: rgba(255,255,255,0.86); font-size: 13px; }
            .th-secondary-promo-copy .th-secondary-promo-cta { display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; padding: 8px 12px; border-radius: 999px; background: #fff; color: #111827; font-size: 12px; font-weight: 800; }
            .th-secondary-promo-cta::after { content: '›'; font-size: 14px; line-height: 1; }
            .th-deal-card { background: #fff; border: 1px solid var(--th-line); transition: transform .18s ease, box-shadow .18s ease; }
            .th-deal-card:hover { transform: translateY(-3px); box-shadow: var(--th-shadow); }
            .th-deal-image-wrap { position: relative; aspect-ratio: 1 / 1; overflow: hidden; background: #f1f1f1; }
            .th-deal-image-wrap img { width: 100%; height: 100%; object-fit: cover; }
            .th-deal-chip, .th-deal-countdown { position: absolute; bottom: 10px; right: 10px; background: rgba(22,22,22,0.68); color: #fff; padding: 4px 8px; border-radius: 999px; font-size: 11px; }
            .th-deal-countdown { top: 10px; left: 10px; right: auto; bottom: auto; }
            .th-deal-body { padding: 12px 12px 14px; }
            .th-deal-title { margin: 0 0 12px; font-size: 15px; line-height: 1.45; min-height: 44px; }
            .th-pricing { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
            .th-price { color: var(--th-red); font-size: 20px; font-weight: 900; letter-spacing: -0.04em; }
            .th-price small { font-size: 18px; }
            .th-discount { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 6px; background: var(--th-red); color: #fff; font-size: 13px; font-weight: 800; }
            .th-old-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; color: #a8a8a8; font-size: 13px; }
            .th-old-price { text-decoration: line-through; }
            .th-stat { color: #9d9d9d; }
            .th-category-section { margin-top: 26px; background: var(--th-surface); border: 1px solid var(--th-line); }
            .th-category-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 16px; min-height: 52px; border-top: 4px solid var(--th-red); }
            .th-category-title { display: flex; align-items: center; gap: 12px; min-width: 220px; color: var(--th-red); font-size: 18px; font-weight: 900; text-transform: uppercase; }
            .th-category-title-badge { width: 32px; height: 32px; border-radius: 8px; background: currentColor; color: #fff; display: grid; place-items: center; font-size: 16px; }
            .th-category-filters, .th-category-tabs { display: flex; align-items: center; gap: 22px; font-size: 13px; color: #6f6f6f; flex-wrap: wrap; }
            .th-category-tabs span:first-child, .th-category-filters a:first-child { color: var(--th-ink); font-weight: 800; }
            .th-category-grid { padding: 16px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .th-category-footer { padding: 0 16px 18px; display: flex; justify-content: center; }
            .th-more-button { border: 1px solid var(--th-line); background: #fafafa; color: #6f6f6f; padding: 10px 18px; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }
            .th-inline-edit-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; min-height: 32px; padding-right: 96px; position: relative; z-index: 2; pointer-events: none; }
            .th-inline-edit-head > * { pointer-events: auto; }
            .th-inline-edit-head .sf-inline-edit-btn { position: absolute; top: 0; right: 0; z-index: 3; }

            @media (max-width: 1100px) {
                .th-hero-layout { grid-template-columns: 1fr; }
                .th-sidebar { display: none; }
                .th-hero-stack { grid-template-columns: 1fr; }
                .th-card-grid, .th-category-grid, .th-brand-strip, .th-secondary-promo-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .th-search { grid-template-columns: minmax(0, 1fr) 52px; }
                .th-sidebar-mega { display: none !important; opacity: 0 !important; visibility: hidden !important; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { text-align: center; font-size: 36px; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-categories { min-width: 0; }
                .th-main-nav-menu { overflow-x: auto; }
                .th-nav-link { padding: 0 14px; white-space: nowrap; }
                .th-nav-products-panel, .th-nav-simple-panel { display: none; }
                .th-hero-overlay { width: 100%; padding: 24px 18px; }
                .th-card-grid, .th-category-grid, .th-brand-strip, .th-footer-grid, .th-secondary-promo-grid { grid-template-columns: 1fr; }
                .th-category-header { align-items: flex-start; padding: 12px 16px; }
                .th-category-title { min-width: 0; font-size: 22px; }
                .th-category-tabs, .th-category-filters, .th-inline { gap: 12px; }
                .th-price { font-size: 20px; }
                .th-secondary-promo-head { align-items: flex-start; flex-direction: column; }
            }
            .th-interior-page { background: #f6f2ea; color: #21312b; }
            .th-interior-page .th-topbar { border-top-color: #33483c; background: #fbf7ef; color: #6f7a72; }
            .th-interior-page .th-header { background: #fffdf8; border-bottom: 1px solid #e2ddd2; }
            .th-interior-page .th-logo img { width: 166px; }
            .th-interior-page .th-search { border: 1px solid #e2ddd2; border-radius: 999px; max-width: 650px; box-shadow: 0 10px 28px rgba(57,48,38,.05); }
            .th-interior-page .th-search button { background: #33483c; letter-spacing: .08em; text-transform: uppercase; }
            .th-interior-page .th-cart { color: #21312b; text-transform: uppercase; letter-spacing: .08em; }
            .th-interior-page .th-main-nav { background: #fffdf8; color: #21312b; border-top: 1px solid rgba(51,72,60,.08); border-bottom: 1px solid #d8d0c2; box-shadow: 0 14px 32px rgba(57,48,38,.08); }
            .th-interior-page .th-home-link { width: 58px; min-height: 54px; background: #ece6dc; color: #33483c; border-left: 1px solid #d8d0c2; border-right: 1px solid #d8d0c2; font-size: 21px; font-weight: 900; text-shadow: 0 1px 0 rgba(255,255,255,.65); box-shadow: inset 0 -2px 0 rgba(51,72,60,.12); }
            .th-interior-page .th-home-link:hover { background: #33483c; color: #fff; box-shadow: inset 0 -3px 0 #b98455; transform: translateY(-1px); text-shadow: none; }
            .th-interior-page .th-nav-products { background: transparent; }
            .th-interior-page .th-main-nav-menu > .th-nav-item > .th-nav-link { min-height: 54px; padding: 0 24px; color: #17251f; font-size: 13px; font-weight: 950; letter-spacing: .105em; text-shadow: 0 1px 0 rgba(255,255,255,.68); border-right: 1px solid rgba(51,72,60,.08); }
            .th-interior-page .th-main-nav-menu > .th-nav-products > .th-nav-link { background: #33483c; color: #fff; box-shadow: inset 0 -3px 0 rgba(244,208,111,.55); text-shadow: none; }
            .th-interior-page .th-main-nav-menu > .th-nav-products > .th-nav-link .th-nav-caret { color: #f4d06f; }
            .th-interior-page .th-main-nav-menu > .th-nav-item:hover > .th-nav-link,
            .th-interior-page .th-main-nav-menu > .th-nav-item > .th-nav-link:hover { background: #33483c; color: #fff; box-shadow: inset 0 -3px 0 #b98455; transform: translateY(-1px); text-shadow: none; }
            .th-interior-page .th-nav-caret { color: #b98455; }
            .th-interior-page .th-nav-item:hover > .th-nav-link .th-nav-caret { color: #f4d06f; transform: translateY(-1px) rotate(180deg); }
            .th-interior-page .th-nav-simple-panel { background: #fffdf8; border: 1px solid #d8d0c2; box-shadow: 0 22px 54px rgba(33,49,43,.18); }
            .th-interior-page .th-nav-simple-panel a { color: #24362f; font-weight: 800; border-radius: 12px; }
            .th-interior-page .th-nav-simple-panel a:hover { background: #33483c; color: #fff; }
            .th-interior-page .th-nav-products-panel { border-top: 1px solid rgba(51,72,60,.12); border-bottom: 1px solid #d8d0c2; box-shadow: 0 32px 80px rgba(33,49,43,.2); }
            .interior-kicker { color: #b98455; font-size: 12px; font-weight: 900; letter-spacing: .18em; text-transform: uppercase; }
            .interior-button, .interior-button-alt { min-height: 46px; display: inline-flex; align-items: center; justify-content: center; padding: 0 20px; font-size: 12px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
            .interior-button { background: #33483c; color: #fff; }
            .interior-button-alt { border: 1px solid rgba(255,255,255,.35); color: #fff; }
            .th20-hero-promos { margin: 24px 0; display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 18px; align-items: stretch; }
            .interior-banner-slider { position: relative; margin: 0; min-height: 0; overflow: hidden; background: #e3ddd2; border: 1px solid #e2ddd2; border-radius: 28px; box-shadow: 0 22px 60px rgba(57,48,38,.09); }
            .interior-banner-track { position: relative; aspect-ratio: 16 / 6; min-height: 318px; }
            .interior-banner-slide { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .55s ease, visibility .55s ease; }
            .interior-banner-slide.is-active { opacity: 1; visibility: visible; z-index: 1; }
            .interior-banner-slide img { width: 100%; height: 100%; object-fit: cover; filter: saturate(.98) contrast(1.02); }
            .interior-banner-dots { position: absolute; left: 0; right: 0; bottom: 14px; z-index: 2; display: flex; justify-content: center; gap: 8px; pointer-events: none; }
            .interior-banner-dot { width: 26px; height: 3px; border-radius: 999px; background: rgba(255,255,255,.52); box-shadow: 0 1px 8px rgba(31,26,29,.24); transition: width .2s ease, background .2s ease; }
            .interior-banner-dot.is-active { width: 44px; background: #fff; }
            .th20-side-banner-stack { display: grid; gap: 18px; }
            .th20-side-banner { position: relative; min-height: 0; overflow: hidden; border-radius: 28px; border: 1px solid #e2ddd2; background: #33483c; color: #fff; box-shadow: 0 18px 42px rgba(57,48,38,.08); }
            .th20-side-banner::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(22,30,25,.08), rgba(22,30,25,.78)); }
            .th20-side-banner img { width: 100%; height: 100%; min-height: 150px; object-fit: cover; }
            .th20-side-banner span, .th20-side-banner small { position: absolute; z-index: 1; left: 18px; right: 18px; }
            .th20-side-banner span { bottom: 48px; font-size: 20px; font-weight: 800; line-height: 1.2; }
            .th20-side-banner small { bottom: 18px; color: rgba(255,255,255,.78); line-height: 1.45; }
            .interior-content-strip { margin: 22px 0; display: grid; grid-template-columns: minmax(230px, .32fr) minmax(0, 1fr); gap: 18px; align-items: stretch; }
            .interior-strip-head { background: #fffdf8; padding: 28px; border: 1px solid #e2ddd2; border-radius: 28px; display: grid; align-content: center; gap: 12px; box-shadow: 0 14px 38px rgba(57,48,38,.05); }
            .interior-strip-head span, .interior-news-head span { color: #b98455; font-size: 12px; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
            .interior-strip-head h2, .interior-news-head h2 { margin: 0; color: #21312b; font-family: Georgia, 'Times New Roman', serif; font-size: clamp(28px, 4vw, 44px); font-weight: 500; line-height: 1.05; }
            .interior-strip-head p, .interior-news-head p { margin: 0; color: #6f7a72; line-height: 1.75; }
            .interior-menu-rail { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
            .interior-category-tile { min-height: 174px; background: #33483c; color: #fff; padding: 22px; border-radius: 28px; display: grid; align-content: space-between; position: relative; overflow: hidden; box-shadow: 0 16px 38px rgba(57,48,38,.08); }
            .interior-category-tile::after { content: ''; position: absolute; inset: auto -42px -58px auto; width: 130px; height: 130px; border-radius: 999px; background: rgba(234,217,186,.22); }
            .interior-category-tile:nth-child(2) { background: #8f6a4a; }
            .interior-category-tile:nth-child(3) { background: #60715c; }
            .interior-category-tile:nth-child(4) { background: #b98455; }
            .interior-category-icon { width: 38px; height: 38px; display: inline-grid; place-items: center; background: rgba(255,255,255,.14); color: #ead9ba; font-size: 18px; }
            .interior-category-tile strong { position: relative; z-index: 1; font-size: 18px; line-height: 1.25; }
            .interior-category-tile small { position: relative; z-index: 1; color: rgba(255,255,255,.72); line-height: 1.55; }
            .interior-news-section { margin-top: 34px; display: grid; grid-template-columns: minmax(260px, .34fr) minmax(0, 1fr); gap: 24px; align-items: start; }
            .interior-news-head { background: #fffdf8; border: 1px solid #e2ddd2; border-radius: 28px; padding: 28px; display: grid; gap: 14px; }
            .interior-news-link { width: fit-content; min-height: 42px; display: inline-flex; align-items: center; padding: 0 16px; border-radius: 999px; background: #33483c; color: #fff; font-size: 12px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
            .interior-news-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .interior-news-card { background: #fffdf8; border: 1px solid #e2ddd2; border-radius: 24px; min-width: 0; overflow: hidden; box-shadow: 0 14px 34px rgba(57,48,38,.06); }
            .interior-news-image { aspect-ratio: 4 / 3; overflow: hidden; background: #e3ddd2; }
            .interior-news-image img { width: 100%; height: 100%; object-fit: cover; transition: transform .32s ease; }
            .interior-news-card:hover img { transform: scale(1.045); }
            .interior-news-body { padding: 18px; display: grid; gap: 10px; }
            .interior-news-date { color: #b20f3a; font-size: 11px; font-weight: 900; letter-spacing: .14em; text-transform: uppercase; }
            .interior-news-card h3 { margin: 0; color: #1f1a1d; font-size: 18px; line-height: 1.35; }
            .interior-news-card p { margin: 0; color: #756a70; line-height: 1.65; }
            .th-interior-page .th-hero-layout { grid-template-columns: 250px minmax(0, 1fr); align-items: start; }
            .th-interior-page .th-sidebar { border: 0; background: #fff; box-shadow: 0 18px 44px rgba(31,26,29,.08); }
            .th-interior-page .th-sidebar-item { border-bottom-color: #f0e7e2; min-height: 54px; letter-spacing: .03em; }
            .th-interior-page .th-hero-stack { grid-template-columns: minmax(0, 1fr) 250px; }
            .th-interior-page .th-hero-card { min-height: 430px; border: 0; background: #111; }
            .th-interior-page .th-hero-overlay { width: min(50%, 460px); background: linear-gradient(90deg, rgba(31,26,29,.88) 0%, rgba(31,26,29,.15) 100%); padding: 52px 42px; }
            .th-interior-page .th-eyebrow { background: rgba(242,201,76,.18); color: #f2c94c; border-radius: 0; letter-spacing: .16em; }
            .th-interior-page .th-hero-title { font-family: Georgia, 'Times New Roman', serif; font-weight: 500; font-size: clamp(34px, 5vw, 58px); }
            .th-interior-page .th-hero-button { border-radius: 0; background: #fff; color: #1f1a1d; letter-spacing: .1em; }
            .th-interior-page .th-side-promo { min-height: 132px; border: 0; background: #1f1a1d; }
            .th-interior-page .th-side-promo span { top: auto; bottom: 14px; text-transform: uppercase; letter-spacing: .08em; }
            .th-interior-page .th-brand-strip { border: 0; background: transparent; padding: 0; margin-top: 18px; grid-template-columns: repeat(5, minmax(0, 1fr)); }
            .th-interior-page .th-brand-badge { width: 100%; height: 112px; border-radius: 0; box-shadow: none; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; }
            .th-interior-page .th-featured-panel, .th-interior-page .th-category-section, .th-interior-page .th-secondary-promo-section { border: 0; background: transparent; padding: 0; }
            .th-interior-page .th-featured-topbar, .th-interior-page .th-category-header { border: 0; padding: 24px 0 14px; min-height: 0; }
            .th-interior-page .th-section-tabs span:first-child, .th-interior-page .th-category-title { font-family: Georgia, 'Times New Roman', serif; font-size: 34px; color: #21312b; text-transform: none; font-weight: 500; }
            .th-interior-page .th-section-tabs span:not(:first-child), .th-interior-page .th-category-tabs, .th-interior-page .th-category-filters { color: #6f7a72; letter-spacing: .08em; font-size: 12px; }
            .th-interior-page .th-card-grid, .th-interior-page .th-category-grid { padding: 0; gap: 22px; }
            .th-interior-page .th-deal-card { border: 1px solid #e2ddd2; border-radius: 24px; background: #fffdf8; box-shadow: 0 14px 34px rgba(57,48,38,.06); overflow: hidden; }
            .th-interior-page .th-deal-card:hover { transform: translateY(-4px); box-shadow: 0 22px 54px rgba(57,48,38,.12); border-color: #33483c; }
            .th-interior-page .th-deal-image-wrap { aspect-ratio: 4 / 3; background: #e3ddd2; }
            .th-interior-page .th-deal-image-wrap img { transition: transform .32s ease; }
            .th-interior-page .th-deal-card:hover img { transform: scale(1.045); }
            .th-interior-page .th-deal-chip, .th-interior-page .th-deal-countdown { border-radius: 999px; background: #33483c; letter-spacing: .08em; text-transform: uppercase; }
            .th-interior-page .th-deal-body { padding: 16px 18px 18px; }
            .th-interior-page .th-deal-title { min-height: 48px; font-size: 16px; color: #21312b; }
            .th-interior-page .th-price { color: #8f6a4a; letter-spacing: 0; }
            .th-interior-page .th-discount { border-radius: 999px; background: #60715c; }
            .th-interior-page .th-secondary-promo-grid { grid-template-columns: 1.2fr .8fr; }
            .th-interior-page .th-secondary-promo-card { border: 0; border-radius: 28px; min-height: 260px; }
            .th-interior-page .th-footer { background: #27382f; color: #fff; border: 0; }
            .th-interior-page .th-footer-card h4, .th-interior-page .th-company strong { color: #ead9ba; letter-spacing: .12em; }
            .th-interior-page .th-footer-links { color: rgba(255,255,255,.68); }
            .th-interior-page .th-company { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 20px; }
            @media (max-width: 1100px) {
                .th20-hero-promos { grid-template-columns: 1fr; }
                .th20-side-banner-stack { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .interior-content-strip, .interior-news-section { grid-template-columns: 1fr; }
                .interior-menu-rail, .interior-news-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .th-interior-page .th-hero-stack { grid-template-columns: 1fr; }
            }
            @media (max-width: 760px) {
                .th20-side-banner-stack { grid-template-columns: 1fr; }
                .interior-banner-track { aspect-ratio: 16 / 9; min-height: 220px; }
                .interior-menu-rail, .interior-news-grid { grid-template-columns: 1fr; }
                .th-interior-page .th-hero-overlay { width: 100%; padding: 30px 22px; }
                .th-interior-page .th-brand-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .th-interior-page .th-section-tabs span:first-child, .th-interior-page .th-category-title { font-size: 28px; }
            }
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
                    <a class="th-cart" href="{{ route('site.cart.index') }}">?? {{ $cartSummary['count'] ?? 0 }} @themeT('common.cart_label', 'GI? HÀNG')</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <a class="th-home-link" href="{{ route('site.home') }}" aria-label="@themeT('common.home', 'Trang ch?')">¦</a>
                    <div class="th-main-nav-menu">
                        <div class="th-nav-item th-nav-products">
                            <a href="#interior-collections" class="th-nav-link">
                                <span>@themeT('common.products', 'S?n ph?m')</span>
                                <span class="th-nav-caret">?</span>
                            </a>
                            <div class="th-nav-products-panel">
                                <div class="th-nav-products-inner">
                                    <div class="th-nav-products-intro">
                                        <span>@themeT('home.products_menu_kicker', 'TH0020 catalog')</span>
                                        <strong>@themeT('home.products_menu_title', 'Ch?n nhanh danh m?c')</strong>
                                        <small>@themeT('home.products_menu_summary', 'Mega menu full width cho s?n ph?m, gom các nhóm hàng chính và liên k?t con d? khách di th?ng vào dúng outfit.')</small>
                                    </div>
                                    <div class="th-nav-products-grid">
                                        @foreach (collect($sidebarCategories)->take(8) as $category)
                                            <div class="th-nav-category-card">
                                                <a href="{{ $category['url'] ?? route('site.catalog.search') }}" target="{{ $category['target'] ?? '_self' }}" class="th-nav-category-head">
                                                    <span class="th-nav-category-icon">{{ $category['icon'] ?? '?' }}</span>
                                                    <strong>{{ $category['label'] ?? __('common.category') }}</strong>
                                                </a>
                                                <div class="th-nav-category-links">
                                                    @forelse (collect($category['children'] ?? [])->take(4) as $child)
                                                        <a href="{{ $child['url'] ?? ($category['url'] ?? route('site.catalog.search')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.child_group') }}</a>
                                                    @empty
                                                        <a href="{{ $category['url'] ?? route('site.catalog.search') }}" target="{{ $category['target'] ?? '_self' }}">@themeT('home.products_menu_view_all', 'Xem t?t c?')</a>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach ($topMenuItems as $menuItem)
                            <div class="th-nav-item">
                                <a href="{{ $menuItem['url'] ?? route('site.home') }}" target="{{ $menuItem['target'] ?? '_self' }}" class="th-nav-link">
                                    <span>{{ $menuItem['label'] ?? __('common.menu') }}</span>
                                    @if (!empty($menuItem['children']))
                                        <span class="th-nav-caret">?</span>
                                    @endif
                                </a>
                                @if (!empty($menuItem['children']))
                                    <div class="th-nav-simple-panel">
                                        @foreach (collect($menuItem['children'])->take(6) as $child)
                                            <a href="{{ $child['url'] ?? ($menuItem['url'] ?? route('site.home')) }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? __('common.menu') }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </nav>
            </div>

            <main class="th-content">
                <div class="th-container">
                    <section class="th20-hero-promos">
                        <div class="interior-banner-slider" data-interior-banner-slider>
                            <div class="interior-banner-track">
                                @foreach ($InteriorBannerSlides as $slide)
                                    <a
                                        href="{{ $slide['link_url'] ?? route('site.catalog.search') }}"
                                        target="{{ $slide['target'] ?? '_self' }}"
                                        class="interior-banner-slide {{ $loop->first ? 'is-active' : '' }}"
                                        data-interior-banner-slide
                                        aria-label="{{ $slide['title'] ?? ($companyTitle ?: 'TH0020 Interior') }}"
                                    >
                                        <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] ?? ($companyTitle ?: 'TH0020 Interior') }}">
                                    </a>
                                @endforeach
                            </div>
                            @if ($InteriorBannerSlides->count() > 1)
                                <div class="interior-banner-dots" aria-hidden="true">
                                    @foreach ($InteriorBannerSlides as $slide)
                                        <span class="interior-banner-dot {{ $loop->first ? 'is-active' : '' }}" data-interior-banner-dot></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <aside class="th20-side-banner-stack" aria-label="Banner nội thất nổi bật">
                            @foreach ($topSidePromos as $promo)
                                <a href="{{ $promo['link_url'] ?? route('site.catalog.search') }}" target="{{ $promo['target'] ?? '_self' }}" class="th20-side-banner">
                                    <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] ?? 'TH0020 banner' }}">
                                    <span>{{ $promo['title'] ?? 'Bộ sưu tập nội thất' }}</span>
                                    @if (!empty($promo['subtitle']))
                                        <small>{{ $promo['subtitle'] }}</small>
                                    @endif
                                </a>
                            @endforeach
                        </aside>
                    </section>

                    <section id="interior-collections" class="interior-content-strip">
                        <div class="interior-strip-head">
                            <span>@themeT('home.collection_kicker', 'Shop by room')</span>
                            <h2>@themeT('home.collection_title', 'Mua theo không gian')</h2>
                            <p>@themeT('home.collection_summary', 'Đưa danh mục lên thành các tile trực quan để khách chọn nhanh phòng khách, phòng ngủ, bàn ăn hoặc decor.')</p>
                        </div>
                        <div class="interior-menu-rail">
                            @foreach (collect($InteriorCategoryTiles)->take(4) as $category)
                                @php
                                    $categoryChildren = collect($category['children'] ?? [])->pluck('label')->take(3)->implode(' / ');
                                    $categoryCount = (int) ($category['products_count'] ?? 0);
                                @endphp
                                <a href="{{ $category['url'] ?? route('site.catalog.search') }}" target="{{ $category['target'] ?? '_self' }}" class="interior-category-tile">
                                    <span class="interior-category-icon">{{ $category['icon'] ?? '?' }}</span>
                                    <strong>{{ $category['label'] ?? $category['name'] ?? __('common.category') }}</strong>
                                    <small>
                                        {{ $categoryChildren !== '' ? $categoryChildren : $t('home.collection_tile_fallback', 'Hàng m?i / Bán ch?y / Sale') }}
                                        @if ($categoryCount > 0)
                                            · {{ $categoryCount }} @themeT('home.collection_product_count', 's?n ph?m')
                                        @endif
                                    </small>
                                </a>
                            @endforeach
                        </div>
                    </section>

                    <section id="featured" class="th-featured-panel">
                        <div class="th-featured-topbar">
                            <div class="th-section-tabs">
                                <span>{{ $featuredTitle }}</span>
                            </div>
                        </div>

                        <div class="th-card-grid">
                            @foreach ($featuredDeals as $deal)
                                <article class="th-deal-card">
                                    <div class="th-deal-image-wrap">
                                        <a href="{{ $deal['url'] ?? route('site.catalog.search') }}">
                                            <img src="{{ $deal['image'] }}" alt="{{ $deal['title'] }}">
                                        </a>
                                        <span class="th-deal-chip">{{ $deal['tag'] ?? 'S?n ph?m' }}</span>
                                    </div>
                                    <div class="th-deal-body">
                                        <h3 class="th-deal-title"><a href="{{ $deal['url'] ?? route('site.catalog.search') }}">{{ $deal['title'] }}</a></h3>
                                        <div class="th-pricing">
                                            <span class="th-price">{{ $formatCurrency($deal['price'] ?? null) }}</span>
                                            <span class="th-discount">{{ $formatDiscount($deal['discount'] ?? 0) }}</span>
                                        </div>
                                        <div class="th-old-price-row">
                                            <span class="th-old-price">{{ $formatCurrency($deal['old_price'] ?? null) }}</span>
                                            <span class="th-stat">{{ str_replace(':count', (string) ($deal['meta'] ?? 0), $t('home.stock', 'T?n kho :count')) }}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    @foreach ($sections as $section)
                        @php
                            $sectionItems = collect($section['items'] ?? [])->take(4)->all();
                        @endphp
                        <section id="section-{{ $section['slug'] }}" class="th-category-section">
                            <div class="th-category-header">
                                <div class="th-category-title">
                                    <span class="th-category-title-badge">?</span>
                                    <span>{{ $section['title'] }}</span>
                                </div>

                                <div class="th-category-tabs">
                                    @foreach ($section['tabs'] as $tab)
                                        <span>{{ $tab }}</span>
                                    @endforeach
                                </div>

                                <div class="th-category-filters">
                                    @foreach ($section['filters'] as $filter)
                                        <a href="{{ is_array($filter) ? ($filter['url'] ?? ($section['url'] ?? route('site.catalog.search'))) : ($section['url'] ?? route('site.catalog.search')) }}">{{ is_array($filter) ? ($filter['label'] ?? '') : $filter }}</a>
                                    @endforeach
                                </div>
                            </div>

                            <div class="th-category-grid">
                                @foreach ($sectionItems as $item)
                                    <article class="th-deal-card">
                                        <div class="th-deal-image-wrap">
                                            <a href="{{ $item['url'] ?? route('site.catalog.search') }}">
                                                <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}">
                                            </a>
                                            <span class="th-deal-countdown">{{ str_replace(':days', '21', $t('home.interior_days_left', '? Còn :days ngày')) }}</span>
                                            <span class="th-deal-chip">{{ $item['tag'] }}</span>
                                        </div>
                                        <div class="th-deal-body">
                                            <h3 class="th-deal-title"><a href="{{ $item['url'] ?? route('site.catalog.search') }}">{{ $item['title'] }}</a></h3>
                                            <div class="th-pricing">
                                                <span class="th-price">{{ $formatCurrency($item['price'] ?? null) }}</span>
                                                <span class="th-discount">{{ $formatDiscount($item['discount'] ?? 0) }}</span>
                                            </div>
                                            <div class="th-old-price-row">
                                                <span class="th-old-price">{{ $formatCurrency($item['old_price'] ?? null) }}</span>
                                                <span class="th-stat">{{ str_replace(':count', (string) ($item['meta'] ?? 0), $t('home.stock', 'T?n kho :count')) }}</span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>

                            <div class="th-category-footer">
                                <a href="{{ $section['url'] ?? route('site.catalog.category', ['slug' => $section['slug']]) }}" class="th-more-button">{{ str_replace(':title', $section['title'], __('home.view_all_latest')) }}</a>
                            </div>
                        </section>
                    @endforeach

                    @if (!empty($secondarySidePromos))
                        <section class="th-secondary-promo-section">
                            <div class="th-secondary-promo-head">
                                <div>
                                    <h3>@themeT('home.secondary_promos_title', 'Khám phá nhanh')</h3>
                                    <p>@themeT('home.secondary_promos_summary', 'Demo cho location secondary_side_promos d? theme khác tái s? d?ng cùng co ch? qu?n tr?.')</p>
                                </div>
                            </div>

                            <div class="th-secondary-promo-grid">
                                @foreach ($secondarySidePromos as $promo)
                                    <a href="{{ $promo['link_url'] ?? route('site.catalog.search') }}" target="{{ $promo['target'] ?? '_self' }}" class="th-secondary-promo-card">
                                        <img src="{{ $promo['image'] }}" alt="{{ $promo['title'] }}">
                                        @if (!empty($promo['badge']))
                                            <span class="th-secondary-promo-badge">{{ $promo['badge'] }}</span>
                                        @endif
                                        <div class="th-secondary-promo-copy">
                                            <strong>{{ $promo['title'] }}</strong>
                                            <span>{{ $promo['subtitle'] }}</span>
                                            @if (!empty($promo['cta_label']))
                                                <span class="th-secondary-promo-cta">{{ $promo['cta_label'] }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if (!empty($latestPosts))
                        <section id="interior-journal" class="interior-news-section">
                            <div class="interior-news-head">
                                <span>{{ $latestPostsSection['kicker'] ?? 'Interior journal' }}</span>
                                <h2>{{ $latestPostsSection['title'] ?? 'Tin m?i t? shop' }}</h2>
                                <p>{{ $latestPostsSection['summary'] ?? 'C?p nh?t lookbook, cách ph?i d? và các ghi chú v?n hành m?i nh?t t? CMS.' }}</p>
                                <a href="{{ route('site.blog.index') }}" class="interior-news-link">@themeT('home.latest_posts_view_all', 'Xem t?t c?')</a>
                            </div>

                            <div class="interior-news-grid">
                                @foreach (collect($latestPosts)->take(3) as $post)
                                    <article class="interior-news-card">
                                        <a href="{{ $post['url'] ?? route('site.blog.index') }}" class="interior-news-image">
                                            <img src="{{ $post['image'] ?? 'https://picsum.photos/seed/TH0020-news-'.$loop->index.'/900/700' }}" alt="{{ $post['title'] ?? 'Interior journal' }}">
                                        </a>
                                        <div class="interior-news-body">
                                            <span class="interior-news-date">{{ $post['published_at'] ?? now()->format('d/m/Y') }}</span>
                                            <h3><a href="{{ $post['url'] ?? route('site.blog.index') }}">{{ $post['title'] ?? 'Interior journal' }}</a></h3>
                                            <p>{{ $post['excerpt'] ?? $t('home.latest_posts_excerpt_fallback', 'C?p nh?t ng?n cho khách hàng v? s?n ph?m, b? suu t?p và kinh nghi?m ph?i d?.') }}</p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            </main>

            <footer class="th-footer">
                <div class="th-container th-footer-inner">
                    <div class="th-footer-grid">
                        @foreach ($footerColumns as $column)
                            @php
                                $footerColumnEditor = $footerColumnEditFields[$loop->index] ?? ['fields' => []];
                            @endphp
                            <section class="th-footer-card">
                                <div class="th-inline-edit-head">
                                    <h4 data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, sprintf('footer.columns.%d.title', $loop->index)) }}">{{ $column['title'] ?? '' }}</h4>
                                    @if ($canQuickEditThemeBlocks)
                                        <button
                                            type="button"
                                            class="sf-inline-edit-btn"
                                            data-sf-inline-edit-trigger
                                            data-edit-title="S?a {{ $footerColumnEditor['title'] ?? 'c?t footer' }}"
                                            data-edit-fields='@json($footerColumnEditor['fields'] ?? [])'
                                        >
                                            S?a c?t
                                        </button>
                                    @endif
                                </div>
                                <div class="th-footer-links">
                                    @foreach (($column['links'] ?? []) as $linkIndex => $link)
                                        @php
                                            $footerLinkLabel = is_array($link) ? ($link['label'] ?? '') : $link;
                                            $footerLinkUrl = is_array($link) ? ($link['url'] ?? route('site.home')) : route('site.home');
                                        @endphp
                                        <a href="{{ $footerLinkUrl }}" data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, sprintf('footer.columns.%d.links.%d', $loop->parent->index, $linkIndex)) }}">{{ $footerLinkLabel }}</a>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <section class="th-company">
                            <div class="th-inline-edit-head">
                                <strong>{{ mb_strtoupper(data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', '')), 'UTF-8') }}</strong>
                                @if ($canQuickEditThemeBlocks)
                                    <button
                                        type="button"
                                        class="sf-inline-edit-btn"
                                        data-sf-inline-edit-trigger
                                        data-edit-title="S?a chân trang công ty"
                                        data-edit-fields='@json($companyFooterEditFields)'
                                    >
                                        S?a công ty
                                    </button>
                                @endif
                            </div>
                            <div class="th-footer-links">
                                <span data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, 'company_footer.address_line_1') }}">{{ $companyFooter['address_line_1'] ?? '332 Luy Bán Bích, Phu?ng Hòa Th?nh, Qu?n Tân Phú, TP.HCM' }}</span>
                                <span data-translation-display="{{ $themeBlockRegistry->contentKey($themeKey, 'company_footer.address_line_2') }}">{{ $companyFooter['address_line_2'] ?? 'Chi nhánh Hà N?i: T?ng 3, CT2 Ban Co Y?u Chính Ph?, Thanh Xuân' }}</span>
                                <span>Hotline: {{ $contactHotline }}</span>
                                <span>Email: {{ $contactEmail }}</span>
                                @include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'th-footer-boc-status'])
                            </div>
                        </section>
                    </div>
                </div>
            </footer>
        </div>
        @include('theme-th0020::partials.product-search-autocomplete')
        @include('theme-th0020::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        @if ($canQuickEditThemeBlocks)
            @include('partials.storefront-inline-translation-editor', [
                'editorId' => 'TH0020-inline-editor',
                'themeKey' => $themeKey,
                'currentLocale' => app()->getLocale(),
                'supportedLocales' => $quickEditLocales,
                'localeOptions' => $quickEditLocaleOptions,
            ])
        @endif
        <script>
            document.querySelectorAll('[data-interior-banner-slider]').forEach((slider) => {
                const slides = Array.from(slider.querySelectorAll('[data-interior-banner-slide]'));
                const dots = Array.from(slider.querySelectorAll('[data-interior-banner-dot]'));
                if (slides.length <= 1) {
                    return;
                }

                let activeIndex = 0;
                window.setInterval(() => {
                    slides[activeIndex].classList.remove('is-active');
                    dots[activeIndex]?.classList.remove('is-active');
                    activeIndex = (activeIndex + 1) % slides.length;
                    slides[activeIndex].classList.add('is-active');
                    dots[activeIndex]?.classList.add('is-active');
                }, 4200);
            });
        </script>
    </body>
</html>
