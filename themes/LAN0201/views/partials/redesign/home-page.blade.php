@php
    $homeMenu = $homeData['top_menu'] ?? [];
    $heroSlidesForBanner = collect($heroSlides ?? [])->take(5)->values()->map(function ($slide, $index): array {
        $fallbackImage = asset('theme-demo/curated/real-estate/projects/project-0'.(($index % 5) + 1).'.svg');
        $resolvedImage = (string) ($slide['image'] ?? data_get($heroBanner ?? [], 'image') ?? '');

        if ($resolvedImage === '' || str_contains($resolvedImage, '/theme-demo/real-estate/hero-main.svg') || str_contains($resolvedImage, '/theme-demo/real-estate/hero-side-')) {
            $resolvedImage = $fallbackImage;
        }

        return [
            'image' => $resolvedImage,
            'title' => $slide['title'] ?? data_get($heroBanner ?? [], 'title') ?? 'Landing mở bán bất động sản',
            'summary' => $slide['summary'] ?? data_get($heroBanner ?? [], 'subtitle') ?? 'LAN0201 được thiết kế lại thành landing page giới thiệu dự án, bảng hàng và lead form.',
            'eyebrow' => $slide['eyebrow'] ?? 'Landing dự án ra mắt',
            'badge' => $slide['badge'] ?? 'Nhận bảng giá và chính sách mới nhất',
            'cta' => $slide['cta'] ?? 'Xem bảng hàng',
            'link_url' => $slide['link_url'] ?? '#bang-hang',
            'alt' => $slide['alt'] ?? $slide['title'] ?? 'Banner mở bán dự án',
        ];
    })->values();

    if ($heroSlidesForBanner->isEmpty()) {
        $heroSlidesForBanner = collect([[
            'image' => asset('theme-demo/curated/real-estate/projects/project-01.svg'),
            'title' => data_get($heroBanner ?? [], 'title') ?? 'Landing mở bán bất động sản',
            'summary' => data_get($heroBanner ?? [], 'subtitle') ?? 'LAN0201 được thiết kế lại thành landing page giới thiệu dự án, bảng hàng và lead form.',
            'eyebrow' => 'Landing dự án ra mắt',
            'badge' => 'Nhận bảng giá và chính sách mới nhất',
            'cta' => 'Xem bảng hàng',
            'link_url' => '#bang-hang',
            'alt' => 'Banner mở bán dự án',
        ]]);
    }

    $launchStories = collect($sections ?? [])->take(3)->map(function ($section, $index): array {
        return [
            'title' => data_get($section, 'title', 'Chủ đề '.($index + 1)),
            'summary' => data_get($section, 'subtitle', data_get($section, 'summary', 'Nội dung đang được cập nhật trong preset LAN0201.')),
            'badge' => data_get($section, 'eyebrow', 'Điểm nhấn mở bán'),
            'image' => data_get($section, 'image')
                ?? data_get($section, 'items.0.image')
                ?? asset('theme-demo/curated/real-estate/projects/project-0'.($index + 1).'.svg'),
        ];
    })->all();

    $categorySpotlights = collect($featuredCategories ?? [])->take(5)->values()->map(function ($categoryItem, $index): array {
        $eyebrows = ['Chọn theo nhịp sống', 'Nhóm khách mua ở', 'Tệp đầu tư giữ dòng tiền', 'Lựa chọn nâng cấp', 'Tệp khách hàng trẻ'];
        $descriptors = [
            'Nhóm sản phẩm có layout linh hoạt, dễ đi tiếp sang bảng hàng và chi tiết căn.',
            'Phù hợp khách cần shortlist nhanh trước khi nhận tư vấn riêng.',
            'Ưu tiên các lựa chọn dễ lọc theo tầm giá, diện tích và vị trí.',
            'Dành cho nhu cầu nâng cấp không gian sống hoặc giữ tài sản dài hạn.',
            'Tối ưu cho hành trình xem nhanh, để lại lead và hẹn lịch private tour.',
        ];
        $tones = ['is-sand', 'is-pearl', 'is-olive', 'is-gold', 'is-ink'];
        $images = [
            'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1600566753151-384129cf4e3e?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1600573472591-ee6b68d14c68?auto=format&fit=crop&w=1200&q=80',
        ];

        return [
            'title' => data_get($categoryItem, 'label') ?: data_get($categoryItem, 'title') ?: 'Phân khu đang mở bán',
            'url' => data_get($categoryItem, 'url', route('site.catalog.search')),
            'chip' => data_get($categoryItem, 'icon') ?: 'Launch',
            'eyebrow' => $eyebrows[$index % count($eyebrows)],
            'descriptor' => data_get($categoryItem, 'summary') ?: data_get($categoryItem, 'subtitle') ?: $descriptors[$index % count($descriptors)],
            'image' => $images[$index % count($images)],
            'tone' => $tones[$index % count($tones)],
            'index' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        ];
    });

    if ($categorySpotlights->isEmpty()) {
        $categorySpotlights = collect(range(1, 4))->map(function ($index): array {
            return [
                'title' => 'Phân khu đang mở bán',
                'url' => route('site.catalog.search'),
                'chip' => 'Launch',
                'eyebrow' => 'Chọn theo nhu cầu',
                'descriptor' => 'Dữ liệu demo đang được làm giàu để hiển thị đa dạng nhóm sản phẩm hơn trong landing này.',
                'image' => [
                    'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=80',
                    'https://images.unsplash.com/photo-1600566753151-384129cf4e3e?auto=format&fit=crop&w=1200&q=80',
                ][($index - 1) % 4],
                'tone' => ['is-sand', 'is-pearl', 'is-olive', 'is-gold'][($index - 1) % 4],
                'index' => str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ];
        });
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'LAN0201 Project Landing')) }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-lan0201::partials.landing-style', ['branding' => $branding])
            .th-home-hero-banner { padding: 0; min-height: 560px; }
            .th-home-hero-slider { position: relative; min-height: 560px; }
            .th-home-hero-slide { position: absolute; inset: 0; opacity: 0; pointer-events: none; transition: opacity .45s ease, transform .45s ease; }
            .th-home-hero-slide.is-active { opacity: 1; pointer-events: auto; }
            .th-home-hero-slide-media { position: absolute; inset: 0; }
            .th-home-hero-slide-media img { width: 100%; height: 100%; object-fit: cover; }
            .th-home-hero-slide-shade { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(17, 26, 38, 0.34) 0%, rgba(17, 26, 38, 0.18) 38%, rgba(17, 26, 38, 0.08) 65%, rgba(17, 26, 38, 0.14) 100%); }
            .th-home-hero-slide-inner { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(300px, .85fr); gap: 26px; align-items: end; min-height: 680px; padding: 42px; }
            .th-home-hero-copy { max-width: 620px; color: #fff8f0; }
            .th-home-hero-copy .th-landing-kicker { background: rgba(255, 244, 224, 0.18); color: #fff1d3; }
            .th-home-hero-copy .th-landing-display { color: #fffaf4; max-width: 10ch; text-shadow: 0 10px 30px rgba(16, 20, 32, 0.24); }
            .th-home-hero-copy .th-landing-summary { color: rgba(255, 248, 240, 0.84); max-width: 56ch; }
            .th-home-hero-copy .th-landing-chip { background: rgba(255, 244, 224, 0.14); color: #fff1d3; border-color: rgba(255, 244, 224, 0.22); }
            .th-home-hero-copy .th-landing-pill { background: rgba(218, 232, 218, 0.18); color: #eef8e9; }
            .th-home-hero-copy .th-landing-tag { background: rgba(255, 255, 255, 0.12); color: #fff8f0; }
            .th-home-hero-panel { display: grid; gap: 16px; padding: 22px; border-radius: 16px; background: rgba(255, 250, 244, 0.88); border: 1px solid rgba(255,255,255,.28); box-shadow: 0 24px 50px rgba(17, 26, 38, 0.16); }
            .th-home-hero-panel .th-landing-title { font-size: 28px; }
            .th-home-hero-panel form { display: grid; gap: 12px; }
            .th-home-hero-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
            .th-home-hero-meta-card { padding: 14px 16px; border-radius: 12px; background: rgba(15, 53, 87, 0.06); border: 1px solid rgba(15, 53, 87, 0.08); }
            .th-home-hero-meta-card strong { display: block; font-size: 14px; letter-spacing: .08em; text-transform: uppercase; color: var(--th-landing-accent-deep); }
            .th-home-hero-meta-card span { display: block; margin-top: 6px; color: var(--th-landing-muted); line-height: 1.6; }
            .th-home-hero-controls { position: absolute; right: 28px; bottom: 28px; z-index: 2; display: flex; align-items: center; gap: 12px; }
            .th-home-hero-arrow { width: 48px; height: 48px; border-radius: 999px; border: 1px solid rgba(255,255,255,.24); background: rgba(17, 26, 38, 0.34); color: #fff; cursor: pointer; }
            .th-home-hero-dots { display: flex; align-items: center; gap: 8px; }
            .th-home-hero-dot { width: 12px; height: 12px; border-radius: 999px; border: 0; background: rgba(255,255,255,.35); cursor: pointer; }
            .th-home-hero-dot.is-active { width: 34px; background: #fff; }
            .th-home-highlight-slider { display: grid; gap: 12px; margin-top: 22px; }
            .th-home-highlight-slider-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .th-home-highlight-slider-note { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(36, 27, 23, 0.52); }
            .th-home-highlight-slider-actions { display: flex; align-items: center; gap: 8px; }
            .th-home-highlight-slider-arrow { width: 42px; height: 42px; border-radius: 999px; border: 1px solid rgba(138, 91, 56, 0.16); background: rgba(255,255,255,.72); color: var(--th-landing-accent-deep); cursor: pointer; }
            .th-home-highlight-slider-arrow:disabled { opacity: .4; cursor: default; }
            .th-home-highlight-viewport { overflow: hidden; }
            .th-home-highlight-track { display: flex; gap: 12px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; scroll-behavior: smooth; padding-bottom: 4px; }
            .th-home-highlight-track::-webkit-scrollbar { display: none; }
            .th-home-highlight { padding: 16px; border-radius: 16px; border: 1px solid var(--th-landing-line); background: rgba(255,255,255,.78); box-shadow: var(--th-landing-shadow); flex: 0 0 calc(33.333% - 8px); scroll-snap-align: start; }
            .th-home-highlight-media { margin: -16px -16px 8px; height: 176px; overflow: hidden; border-radius: 16px 16px 12px 12px; }
            .th-home-highlight-media img { width: 100%; height: 100%; object-fit: cover; }
            .th-home-highlight strong { display: block; font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif; font-size: 16px; line-height: 1.4; }
            .th-home-category-panel { padding: 24px; background: linear-gradient(180deg, rgba(255, 250, 244, 0.96) 0%, rgba(248, 243, 236, 0.98) 100%); }
            .th-home-category-layout { display: grid; grid-template-columns: minmax(260px, .68fr) minmax(0, 1fr); gap: 18px; align-items: stretch; }
            .th-home-category-intro { display: grid; align-content: space-between; gap: 22px; padding: 6px 4px 6px 0; }
            .th-home-category-intro .th-landing-section-title { margin: 14px 0 0; font-size: clamp(34px, 4vw, 54px); line-height: .98; max-width: 10ch; }
            .th-home-category-intro-copy { margin-top: 16px; max-width: 34ch; color: var(--th-landing-muted); line-height: 1.8; }
            .th-home-category-intro-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .th-home-category-intro-stat { padding: 16px; border-radius: 14px; border: 1px solid rgba(138, 91, 56, 0.12); background: rgba(255, 255, 255, 0.58); }
            .th-home-category-intro-stat strong { display: block; font-size: 24px; color: var(--th-landing-accent-deep); }
            .th-home-category-intro-stat span { display: block; margin-top: 6px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--th-landing-muted); }
            .th-home-category-slider { display: grid; gap: 12px; }
            .th-home-category-slider-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .th-home-category-slider-note { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(36, 27, 23, 0.52); }
            .th-home-category-slider-actions { display: flex; align-items: center; gap: 8px; }
            .th-home-category-slider-arrow { width: 42px; height: 42px; border-radius: 999px; border: 1px solid rgba(138, 91, 56, 0.16); background: rgba(255,255,255,.72); color: var(--th-landing-accent-deep); cursor: pointer; }
            .th-home-category-slider-arrow:disabled { opacity: .4; cursor: default; }
            .th-home-category-viewport { overflow: hidden; }
            .th-home-category-track { display: flex; gap: 12px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; scroll-behavior: smooth; padding-bottom: 4px; }
            .th-home-category-track::-webkit-scrollbar { display: none; }
            .th-home-category-card { position: relative; overflow: hidden; min-height: 176px; padding: 0; border-radius: 18px; border: 1px solid rgba(138, 91, 56, 0.12); background: rgba(255,255,255,.82); box-shadow: 0 18px 34px rgba(68, 46, 31, 0.08); display: grid; gap: 0; align-content: start; flex: 0 0 calc(50% - 6px); scroll-snap-align: start; }
            .th-home-category-card-media { position: relative; height: 148px; overflow: hidden; border-radius: 18px 18px 0 0; }
            .th-home-category-card-media::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(16, 22, 31, 0.08) 0%, rgba(16, 22, 31, 0.32) 100%); }
            .th-home-category-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .th-home-category-card-content { display: grid; gap: 10px; padding: 12px 22px 18px; }
            .th-home-category-card-top,
            .th-home-category-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .th-home-category-card-mark { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font-size: 13px; font-weight: 800; letter-spacing: .14em; color: var(--th-landing-accent-deep); background: rgba(255,255,255,.66); border: 1px solid rgba(138, 91, 56, 0.12); }
            .th-home-category-card-body { display: grid; gap: 6px; max-width: 38ch; }
            .th-home-category-card-eyebrow { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(36, 27, 23, 0.56); }
            .th-home-category-card h3 { margin: 0; font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif; font-size: 30px; line-height: 1.02; }
            .th-home-category-card p { margin: 0; color: var(--th-landing-muted); line-height: 1.7; }
            .th-home-category-card-footer { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(36, 27, 23, 0.6); }
            .th-home-category-card-arrow { font-size: 18px; line-height: 1; }
            .th-home-category-card.is-sand { background: linear-gradient(135deg, rgba(255,248,239,.96) 0%, rgba(248,236,222,.94) 100%); }
            .th-home-category-card.is-pearl { background: linear-gradient(135deg, rgba(255,252,248,.98) 0%, rgba(247,242,236,.94) 100%); }
            .th-home-category-card.is-olive { background: linear-gradient(135deg, rgba(244,246,240,.96) 0%, rgba(233,238,228,.94) 100%); }
            .th-home-category-card.is-gold { background: linear-gradient(135deg, rgba(252,246,232,.98) 0%, rgba(244,232,205,.94) 100%); }
            .th-home-category-card.is-ink { background: linear-gradient(135deg, rgba(237,243,248,.98) 0%, rgba(225,233,240,.94) 100%); }
            .th-home-story-card { padding: 24px; }
            .th-home-promo-grid { display: grid; grid-template-columns: 1.15fr .85fr; gap: 20px; }
            .th-home-promo-stack { display: grid; gap: 20px; }
            .th-home-promo { position: relative; overflow: hidden; min-height: 220px; }
            .th-home-promo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .th-home-promo::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(23,16,12,.08) 0%, rgba(23,16,12,.74) 100%); }
            .th-home-promo-copy { position: absolute; inset: auto 18px 18px 18px; z-index: 1; color: #fff; }
            .th-home-promo-copy strong { display: block; font-family: "Iowan Old Style", "Palatino Linotype", "Book Antiqua", Georgia, serif; font-size: 30px; }
            .th-home-promo-copy span { display: block; margin-top: 8px; line-height: 1.7; color: rgba(255,255,255,.84); }
            @media (max-width: 1100px) {
                .th-home-hero-banner,
                .th-home-hero-slider,
                .th-home-hero-slide-inner { min-height: 0; }
                .th-home-hero-slide-inner { grid-template-columns: 1fr; align-items: start; padding: 32px; }
                .th-home-hero-copy { max-width: none; }
                .th-home-hero-copy .th-landing-display { max-width: none; }
                .th-home-hero-controls { left: 24px; right: auto; }
                .th-home-highlight { flex-basis: calc(50% - 6px); }
                .th-home-category-layout,
                .th-home-category-deck { grid-template-columns: 1fr; }
                .th-home-category-card { flex-basis: calc(70% - 6px); }
                .th-home-promo-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 760px) {
                .th-home-hero-banner,
                .th-home-hero-slider { min-height: 420px; }
                .th-home-hero-slide-inner { padding: 20px; }
                .th-home-hero-panel .th-landing-title { font-size: 24px; }
                .th-home-hero-meta { grid-template-columns: 1fr; }
                .th-home-hero-controls { position: static; padding: 0 20px 20px; }
                .th-home-highlight { flex-basis: 84%; }
                .th-home-category-panel { padding: 20px; }
                .th-home-category-intro .th-landing-section-title { max-width: none; }
                .th-home-category-intro-meta { grid-template-columns: 1fr; }
                .th-home-category-card h3 { font-size: 24px; }
                .th-home-category-card { flex-basis: 84%; }
                .th-home-category-slider-head { align-items: flex-start; }
            }
        </style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', [
                    'branding' => $branding,
                    'topMenu' => $homeMenu,
                    'customerAuth' => $customerAuth,
                    'newsletterState' => $newsletterState,
                    'cartSummary' => $cartSummary,
                    'contactHotline' => $contactHotline,
                    'contactEmail' => $contactEmail,
                    'contactLocation' => $contactLocation,
                    'activeNav' => 'home',
                ])

                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <section class="th-landing-hero th-home-hero-banner" data-hero-slider>
                            <div class="th-home-hero-slider">
                                @foreach ($heroSlidesForBanner as $slide)
                                    <article class="th-home-hero-slide {{ $loop->first ? 'is-active' : '' }}" data-hero-slide aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                                        <div class="th-home-hero-slide-media">
                                            <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] }}">
                                        </div>
                                        <div class="th-home-hero-slide-shade"></div>
                                    </article>
                                @endforeach
                            </div>
                            @if ($heroSlidesForBanner->count() > 1)
                                <div class="th-home-hero-controls">
                                    <button type="button" class="th-home-hero-arrow" data-hero-prev aria-label="Slide trước">&larr;</button>
                                    <div class="th-home-hero-dots">
                                        @foreach ($heroSlidesForBanner as $slide)
                                            <button type="button" class="th-home-hero-dot {{ $loop->first ? 'is-active' : '' }}" data-hero-dot aria-label="Đi tới slide {{ $loop->iteration }}"></button>
                                        @endforeach
                                    </div>
                                    <button type="button" class="th-home-hero-arrow" data-hero-next aria-label="Slide tiếp theo">&rarr;</button>
                                </div>
                            @endif
                        </section>

                        <section class="th-home-highlight-slider" data-highlight-slider>
                            <div class="th-home-highlight-slider-head">
                                <span class="th-home-highlight-slider-note">Lướt ngang để xem nhanh các điểm nhấn</span>
                                <div class="th-home-highlight-slider-actions">
                                    <button type="button" class="th-home-highlight-slider-arrow" data-highlight-prev aria-label="Xem item trước">&larr;</button>
                                    <button type="button" class="th-home-highlight-slider-arrow" data-highlight-next aria-label="Xem item tiếp theo">&rarr;</button>
                                </div>
                            </div>
                            <div class="th-home-highlight-viewport">
                                <div class="th-home-highlight-track">
                                    @foreach ($serviceLanes as $lane)
                                        <article class="th-home-highlight">
                                            <div class="th-home-highlight-media">
                                                <img src="{{ $lane['image'] ?? asset('theme-demo/curated/real-estate/projects/project-0'.(($loop->index % 5) + 1).'.svg') }}" alt="{{ $lane['title'] }}">
                                            </div>
                                            <strong>{{ $lane['title'] }}</strong>
                                        </article>
                                    @endforeach
                                    @foreach ($launchStories as $story)
                                        <article class="th-home-highlight">
                                            <div class="th-home-highlight-media">
                                                <img src="{{ $story['image'] }}" alt="{{ $story['title'] }}">
                                            </div>
                                            <strong>{{ $story['title'] }}</strong>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section style="margin-top:28px;">
                            <div class="th-landing-panel th-home-category-panel">
                                <div class="th-home-category-layout">
                                    <div class="th-home-category-intro">
                                        <div>
                                            <span class="th-landing-kicker">Phân khúc mở bán</span>
                                            <h2 class="th-landing-section-title">Danh mục listing được kể lại theo kiểu landing dự án</h2>
                                            <p class="th-home-category-intro-copy">Khối này được làm lại như một bộ chọn phân khúc. Khách có thể vào nhanh từng nhóm nhu cầu trước khi đi tiếp sang bảng hàng, chi tiết căn và luồng tư vấn.</p>
                                        </div>
                                        <div class="th-home-category-intro-meta">
                                            <div class="th-home-category-intro-stat">
                                                <strong>{{ str_pad((string) collect($categorySpotlights)->count(), 2, '0', STR_PAD_LEFT) }}</strong>
                                                <span>Nhóm đang hiển thị</span>
                                            </div>
                                            <div class="th-home-category-intro-stat">
                                                <strong>01 chạm</strong>
                                                <span>Đi tiếp sang bảng hàng</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="th-home-category-slider" data-category-slider>
                                        <div class="th-home-category-slider-head">
                                            <span class="th-home-category-slider-note">Lướt ngang để xem nhanh từng nhóm mở bán</span>
                                            <div class="th-home-category-slider-actions">
                                                <button type="button" class="th-home-category-slider-arrow" data-category-prev aria-label="Xem nhóm trước">&larr;</button>
                                                <button type="button" class="th-home-category-slider-arrow" data-category-next aria-label="Xem nhóm tiếp theo">&rarr;</button>
                                            </div>
                                        </div>
                                        <div class="th-home-category-viewport">
                                            <div class="th-home-category-track">
                                                @foreach ($categorySpotlights as $categoryItem)
                                                    <a href="{{ $categoryItem['url'] }}" class="th-home-category-card {{ $categoryItem['tone'] }}">
                                                        <div class="th-home-category-card-media">
                                                            <img src="{{ $categoryItem['image'] }}" alt="{{ $categoryItem['title'] }}">
                                                        </div>
                                                        <div class="th-home-category-card-content">
                                                            <div class="th-home-category-card-top">
                                                                <span class="th-home-category-card-mark">{{ $categoryItem['index'] }}</span>
                                                                <span class="th-landing-chip">{{ $categoryItem['chip'] }}</span>
                                                            </div>
                                                            <div class="th-home-category-card-body">
                                                                <span class="th-home-category-card-eyebrow">{{ $categoryItem['eyebrow'] }}</span>
                                                                <h3>{{ $categoryItem['title'] }}</h3>
                                                                <p>{{ $categoryItem['descriptor'] }}</p>
                                                            </div>
                                                            <div class="th-home-category-card-footer">
                                                                <span>Xem bảng hàng</span>
                                                                <span class="th-home-category-card-arrow">&rarr;</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="bang-hang" style="margin-top:28px;">
                            <div class="th-landing-panel">
                                <div class="th-landing-two-col" style="align-items:end; margin-bottom:20px;">
                                    <div>
                                        <span class="th-landing-kicker">Bảng hàng nổi bật</span>
                                        <h2 class="th-landing-section-title">{{ $featuredTitle }}</h2>
                                    </div>
                                    <div class="th-landing-copy">Khác với TH0002, khối này không sử dụng shell storefront dạng danh mục sản phẩm. Nó được trình bày như bảng hàng launch, ưu tiên storytelling, giá trị căn và CTA nhận lead.</div>
                                </div>
                                <div class="th-landing-grid-listings">
                                    @foreach (collect($featuredDeals)->take(6) as $product)
                                        <article class="th-landing-listing">
                                            <a href="{{ $product['url'] ?? '#' }}">
                                                <div class="th-landing-listing-media">
                                                    <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/lan0201-product/'.($loop->index + 1).'/720/900' }}" alt="{{ $product['title'] ?? 'Listing' }}">
                                                    <div class="th-landing-listing-badge th-landing-chip">{{ $product['tag'] ?? 'Mở bán' }}</div>
                                                </div>
                                            </a>
                                            <div class="th-landing-listing-body">
                                                <h3 class="th-landing-listing-title"><a href="{{ $product['url'] ?? '#' }}">{{ $product['title'] ?? 'Listing mở bán' }}</a></h3>
                                                <div class="th-landing-price">
                                                    <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                                                    @if (!empty($product['original_price']))
                                                        <span>{{ $formatCurrency($product['original_price']) }}</span>
                                                    @endif
                                                </div>
                                                <div class="th-landing-copy">{{ $product['summary'] ?? 'Thông tin căn, vị trí, ưu đãi và lead note được map trực tiếp từ CatalogProduct.' }}</div>
                                                <div class="th-landing-meta-row">
                                                    @if (!empty($product['sku']))
                                                        <span class="th-landing-chip">{{ $product['sku'] }}</span>
                                                    @endif
                                                    @if (!empty($product['sold_count']))
                                                        <span class="th-landing-pill">{{ number_format((int) $product['sold_count'], 0, ',', '.') }} lead</span>
                                                    @endif
                                                    <span class="th-landing-tag">{{ number_format((int) ($product['meta'] ?? 0), 0, ',', '.') }} căn đang mở</span>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        @if (!empty($secondarySidePromos) || !empty($sidePromos))
                            <section style="margin-top:28px;">
                                <div class="th-home-promo-grid">
                                    @php
                                        $primaryPromo = collect($secondarySidePromos)->first() ?? collect($sidePromos)->first();
                                        $secondaryPromos = collect($secondarySidePromos)->slice(1)->merge(collect($sidePromos)->take(2))->take(2);
                                    @endphp
                                    @if ($primaryPromo)
                                        <a href="{{ $primaryPromo['link_url'] ?? route('site.blog.index') }}" class="th-home-promo th-landing-card">
                                            <img src="{{ $primaryPromo['image'] ?? 'https://picsum.photos/seed/lan0201-promo-main/1200/900' }}" alt="{{ $primaryPromo['title'] ?? 'Điểm sáng dự án' }}">
                                            <div class="th-home-promo-copy">
                                                <span class="th-landing-kicker">Điểm sáng chiến dịch</span>
                                                <strong>{{ $primaryPromo['title'] ?? 'Khối thông tin dự án' }}</strong>
                                                <span>{{ $primaryPromo['subtitle'] ?? 'Sử dụng các block hình ảnh và thông điệp ngắn để đẩy lead về trang bảng hàng hoặc blog.' }}</span>
                                            </div>
                                        </a>
                                    @endif
                                    <div class="th-home-promo-stack">
                                        @foreach ($secondaryPromos as $promo)
                                            <a href="{{ $promo['link_url'] ?? route('site.blog.index') }}" class="th-home-promo th-landing-card">
                                                <img src="{{ $promo['image'] ?? 'https://picsum.photos/seed/lan0201-promo/'.($loop->index + 1).'/900/700' }}" alt="{{ $promo['title'] ?? 'Cập nhật dự án' }}">
                                                <div class="th-home-promo-copy">
                                                    <strong>{{ $promo['title'] ?? 'Tiện ích và tiến độ' }}</strong>
                                                    <span>{{ $promo['subtitle'] ?? 'Đại diện cho các slot nội dung được đem sang một visual language landing-page.' }}</span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                    </div>
                </main>

                @include('theme-lan0201::partials.landing-footer', [
                    'branding' => $branding,
                    'siteProfile' => $siteProfile ?? null,
                    'footerColumns' => $footerColumns,
                ])
            </div>
        </div>

        @include('theme-lan0201::partials.engagement-modals', [
            'customerAuth' => $customerAuth,
            'newsletterState' => $newsletterState,
            'postLoginRedirect' => $postLoginRedirect,
        ])
        <script>
            document.querySelectorAll('[data-hero-slider]').forEach((root) => {
                const slides = Array.from(root.querySelectorAll('[data-hero-slide]'));
                const dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
                const prevButton = root.querySelector('[data-hero-prev]');
                const nextButton = root.querySelector('[data-hero-next]');

                if (slides.length <= 1) {
                    return;
                }

                let activeIndex = 0;
                let timerId = null;

                const render = (index) => {
                    activeIndex = (index + slides.length) % slides.length;

                    slides.forEach((slide, slideIndex) => {
                        const isActive = slideIndex === activeIndex;
                        slide.classList.toggle('is-active', isActive);
                        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                    });

                    dots.forEach((dot, dotIndex) => {
                        dot.classList.toggle('is-active', dotIndex === activeIndex);
                    });
                };

                const start = () => {
                    if (timerId !== null) {
                        window.clearInterval(timerId);
                    }

                    timerId = window.setInterval(() => {
                        render(activeIndex + 1);
                    }, 5200);
                };

                prevButton?.addEventListener('click', () => {
                    render(activeIndex - 1);
                    start();
                });

                nextButton?.addEventListener('click', () => {
                    render(activeIndex + 1);
                    start();
                });

                dots.forEach((dot, dotIndex) => {
                    dot.addEventListener('click', () => {
                        render(dotIndex);
                        start();
                    });
                });

                root.addEventListener('mouseenter', () => {
                    if (timerId !== null) {
                        window.clearInterval(timerId);
                    }
                });

                root.addEventListener('mouseleave', start);
                root.addEventListener('focusin', () => {
                    if (timerId !== null) {
                        window.clearInterval(timerId);
                    }
                });
                root.addEventListener('focusout', start);

                render(0);
                start();
            });

            document.querySelectorAll('[data-category-slider]').forEach((root) => {
                const track = root.querySelector('.th-home-category-track');
                const prevButton = root.querySelector('[data-category-prev]');
                const nextButton = root.querySelector('[data-category-next]');

                if (!track) {
                    return;
                }

                const getStep = () => {
                    const firstCard = track.querySelector('.th-home-category-card');

                    if (!firstCard) {
                        return 0;
                    }

                    const gap = Number.parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap || '12');

                    return firstCard.getBoundingClientRect().width + gap;
                };

                const syncButtons = () => {
                    const maxScroll = track.scrollWidth - track.clientWidth;
                    const current = Math.ceil(track.scrollLeft);

                    if (prevButton) {
                        prevButton.disabled = current <= 0;
                    }

                    if (nextButton) {
                        nextButton.disabled = current >= maxScroll - 2;
                    }
                };

                prevButton?.addEventListener('click', () => {
                    track.scrollBy({ left: -getStep(), behavior: 'smooth' });
                });

                nextButton?.addEventListener('click', () => {
                    track.scrollBy({ left: getStep(), behavior: 'smooth' });
                });

                track.addEventListener('scroll', syncButtons, { passive: true });
                window.addEventListener('resize', syncButtons);
                syncButtons();
            });

            document.querySelectorAll('[data-highlight-slider]').forEach((root) => {
                const track = root.querySelector('.th-home-highlight-track');
                const prevButton = root.querySelector('[data-highlight-prev]');
                const nextButton = root.querySelector('[data-highlight-next]');

                if (!track) {
                    return;
                }

                const getStep = () => {
                    const firstCard = track.querySelector('.th-home-highlight');

                    if (!firstCard) {
                        return 0;
                    }

                    const styles = window.getComputedStyle(track);
                    const gap = Number.parseFloat(styles.columnGap || styles.gap || '12');

                    return firstCard.getBoundingClientRect().width + gap;
                };

                const syncButtons = () => {
                    const maxScroll = track.scrollWidth - track.clientWidth;
                    const current = Math.ceil(track.scrollLeft);

                    if (prevButton) {
                        prevButton.disabled = current <= 0;
                    }

                    if (nextButton) {
                        nextButton.disabled = current >= maxScroll - 2;
                    }
                };

                prevButton?.addEventListener('click', () => {
                    track.scrollBy({ left: -getStep(), behavior: 'smooth' });
                });

                nextButton?.addEventListener('click', () => {
                    track.scrollBy({ left: getStep(), behavior: 'smooth' });
                });

                track.addEventListener('scroll', syncButtons, { passive: true });
                window.addEventListener('resize', syncButtons);
                syncButtons();
            });
        </script>
    </body>
</html>