@php
    $blocks = collect($landingBlocks ?? [])->values();

    if ($blocks->isEmpty()) {
        $blocks = collect([
            ['id' => null, 'block_type' => 'hero_slider', 'anchor_id' => 'top', 'is_visible' => true, 'settings' => ['autoplay_ms' => 5200], 'media' => [], 'data' => ['subtitle' => 'Trang chủ', 'title' => 'Xây dựng ngôi nhà mơ ước', 'description' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'button_label' => 'Xem dự án →', 'content' => ['slides' => [
                ['kicker' => 'Residential', 'title' => 'Xây dựng ngôi nhà mơ ước', 'summary' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85'],
                ['kicker' => 'Commercial', 'title' => 'Thi công không gian kinh doanh', 'summary' => 'Đội ngũ kỹ sư và kiến trúc sư phối hợp để bàn giao showroom, văn phòng, khách sạn đúng chuẩn vận hành.', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1920&q=85'],
                ['kicker' => 'Planning', 'title' => 'Quản lý dự án minh bạch', 'summary' => 'Quy trình báo cáo theo mốc, nghiệm thu từng hạng mục và tối ưu chi phí ngay từ giai đoạn thiết kế.', 'image' => 'https://images.unsplash.com/photo-1485083269755-a7b559a4fe5e?auto=format&fit=crop&w=1920&q=85'],
            ]]]],
            ['id' => null, 'block_type' => 'about_experience', 'anchor_id' => 'gioi-thieu', 'is_visible' => true, 'settings' => ['years' => 10, 'cta_url' => '/gioi-thieu'], 'media' => ['image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1000&q=85'], 'data' => ['subtitle' => 'Giới thiệu', 'title' => 'Thiết kế và thi công Nhà ở, Tòa nhà văn phòng.', 'description' => 'ARKIT là công ty chuyên về thiết kế và thi công. Được thành lập và phát triển bởi các kiến trúc sư, kỹ sư nhiều năm kinh nghiệm.', 'button_label' => 'Tìm hiểu thêm', 'content' => []]],
        ]);
    }

    $hero = $blocks->firstWhere('block_type', 'hero_slider') ?? [];
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect(data_get($hero, 'dynamic_items', []))->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
    $heroSlides = $heroSlides->isNotEmpty() ? $heroSlides : collect([['kicker' => 'Residential', 'title' => 'Xây dựng ngôi nhà mơ ước', 'summary' => 'XD0301 Construction Landing', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85']]);
    $hasMeaningfulHeroText = function (array $slide, array $data = []): bool {
        $values = [
            $slide['kicker'] ?? $data['subtitle'] ?? null,
            $slide['title'] ?? $data['title'] ?? null,
            $slide['summary'] ?? $data['description'] ?? null,
            $slide['button_label'] ?? $data['button_label'] ?? null,
        ];

        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '' && ! preg_match('/^[\d\s.,:;!?\-+_#]+$/u', $text)) {
                return true;
            }
        }

        return false;
    };

    $localizeMenuUrl = function (?string $href): string {
        $href = trim((string) $href);

        if ($href === '' || $href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href !== '' ? $href : '#';
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return route('site.home').$query.$fragment;
        }

        $segments = explode('/', $path);
        $knownLocales = \App\Support\FrontendLocalization::knownLocaleCodes();

        if (! in_array($segments[0] ?? '', $knownLocales, true)) {
            array_unshift($segments, app()->getLocale());
        }

        return url('/'.implode('/', $segments)).$query.$fragment;
    };

    $normalizeNavItem = function (array $item, int $index = 0) use (&$normalizeNavItem, $localizeMenuUrl): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $localizeMenuUrl($href),
            'target' => $item['target'] ?? '_self',
            'active' => false,
            'children' => collect($item['children'] ?? [])
                ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                ->map(fn (array $child, int $childIndex): array => $normalizeNavItem($child, $childIndex))
                ->values()
                ->all(),
        ];
    };

    $cmsNavItems = collect(data_get($themeHomeData ?? $homeData ?? [], 'top_menu', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item, int $index): array => $normalizeNavItem($item, $index))
        ->values();

    $navItems = $cmsNavItems->isNotEmpty()
        ? $cmsNavItems
        : collect($landingMenuItems ?? [])
            ->whenEmpty(fn () => $blocks
                ->filter(fn ($block) => filled($block['anchor_id'] ?? null))
                ->map(fn ($block) => ['label' => data_get($block, 'data.subtitle') ?: data_get($block, 'data.title') ?: \Illuminate\Support\Str::headline($block['block_type']), 'url' => '#'.$block['anchor_id']]))
            ->map(fn ($item, $index) => $normalizeNavItem($item, $index))
            ->values();

    $footerNavItems = collect(data_get($menus ?? [], 'footer', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item, int $index): array => $normalizeNavItem($item, $index))
        ->values();

    $homeUrl = route('site.home');
    $homeLabel = app(\App\Core\Themes\ThemeTranslationService::class)
        ->bladeText((string) data_get($activeTheme ?? [], 'key', 'XD0301'), app()->getLocale(), 'common.home', 'Trang chủ');
    $hasHomeItem = $navItems->contains(function (array $item) use ($homeUrl): bool {
        $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
        $href = rtrim((string) ($item['href'] ?? ''), '/');

        return in_array($label, ['trang chủ', 'home'], true) || $href === rtrim($homeUrl, '/');
    });

    if (! $hasHomeItem) {
        $navItems->prepend([
            'label' => $homeLabel,
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems
                ->map(function (array $item) use ($productNavigationItems): array {
                    $label = mb_strtolower(trim((string) ($item['label'] ?? '')));

                    if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $currentUrl = rtrim(url()->current(), '/');
    $navItems = $navItems->map(function (array $item) use ($currentUrl): array {
        $href = (string) ($item['href'] ?? '#');
        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
        $item['active'] = (bool) ($item['active'] ?? false) || ($href !== '#' && $absoluteHref === $currentUrl);

        return $item;
    })->values();

    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', [])));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $companyDescription = trim((string) ($branding['company_description'] ?? data_get($siteProfile ?? [], 'description', 'Arkit là công ty chuyên về thiết kế và thi công.'))) ?: 'Arkit là công ty chuyên về thiết kế và thi công.';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = $companyName;
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $supportEmail = trim((string) ($branding['support_email'] ?? $branding['email'] ?? 'admin@htvietnam.vn')) ?: 'admin@htvietnam.vn';
    $supportAddress = trim((string) ($branding['support_location'] ?? $branding['address'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM')) ?: '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM';
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))
        ->map(fn (array $locale): array => [
            'code' => (string) ($locale['code'] ?? ''),
            'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? '')),
        ])
        ->filter(fn (array $locale): bool => $locale['code'] !== '')
        ->values()
        ->all() : [];
@endphp

@extends('theme-xd0301::layout')

@section('title', data_get($landingPage ?? [], 'meta_title') ?: data_get($landingPage ?? [], 'title', 'XD0301 Construction Landing'))

@section('content')
<main>
    @foreach ($blocks->where('block_type', '!=', 'footer_contact') as $block)
                @php
                    $data = $block['data'] ?? [];
                    $content = $data['content'] ?? [];
                    $settings = $block['settings'] ?? [];
                    $media = $block['media'] ?? [];
                    $anchor = $block['anchor_id'] ?: $block['block_type'];
                    $editButton = $canEditLanding && filled($block['id'] ?? null)
                        ? '<button type="button" class="xd-edit-block" data-xd-edit-block="'.e((string) $block['id']).'">Sửa khối</button>'
                        : '';
                @endphp

        @includeIf('theme-xd0301::partials.blocks.'.($block['block_type'] ?? ''))
    @endforeach
</main>
@endsection

@push('scripts')
    @include('theme-xd0301::partials.scripts')
@endpush
