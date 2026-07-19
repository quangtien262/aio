@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = [];
    $block = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block): array {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        if ($dynamic->isNotEmpty()) {
            return $dynamic->all();
        }
        return collect(data_get($block, 'data.content.items', []))->filter()->values()->all();
    };
    $hero = $block('hero_slider');
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    if ($heroSlides->isEmpty()) {
        $heroSlides = collect($hero['dynamic_items'] ?? []);
    }
    if ($heroSlides->isEmpty()) {
        $heroSlides = collect([[
            'title' => 'Ẩm thực được kể bằng ký ức',
            'summary' => 'Một bàn ăn ấm áp, những nguyên liệu theo mùa và trải nghiệm được chuẩn bị dành riêng cho từng vị khách.',
            'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=2200&q=90',
            'button_label' => 'Khám phá thực đơn',
            'link_url' => '#thuc-don',
        ]]);
    }
    $serviceBlock = $block('content_mosaic');
    $aboutBlock = $block('about_experience');
    $productBlock = $block('featured_products');
    $newsBlock = $block('bizmax_latest_posts');
    $teamBlock = $block('team_members');
    $serviceItems = $items($serviceBlock);
    $productItems = $items($productBlock);
    $newsItems = $items($newsBlock);
    $teamItems = $items($teamBlock);
    $aboutImage = data_get($aboutBlock, 'media.image', data_get($aboutBlock, 'data.content.image', 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1000&q=85'));
    $formatPrice = fn ($value) => $value === null || (float) $value <= 0 ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp

@extends('theme-foot401::layout')

@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'FOOT401 Restaurant')))

@section('content')
    <main>
        <section class="foot-hero" aria-label="{{ data_get($hero, 'data.title', 'Restaurant hero') }}">
            @foreach ($heroSlides as $index => $slide)
                <article class="foot-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-foot-hero-slide>
                    <img src="{{ data_get($slide, 'image', 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=2200&q=90') }}" alt="{{ data_get($slide, 'title', '') }}">
                    <div class="foot-hero__shade"></div>
                    <div class="foot-container foot-hero__content">
                        <p>{{ data_get($hero, 'data.subtitle', 'FOOT401 Restaurant') }}</p>
                        <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title', 'Ẩm thực được kể bằng ký ức')) }}</h1>
                        <div>{{ data_get($slide, 'summary', data_get($hero, 'data.description', '')) }}</div>
                        <a class="foot-button" href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#thuc-don')) }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Khám phá thực đơn')) }}</a>
                    </div>
                </article>
            @endforeach
        </section>

        <section id="dich-vu" class="foot-section foot-section--paper">
            <div class="foot-container">
                <header class="foot-section-heading"><p>{{ data_get($serviceBlock, 'data.subtitle', 'Trải nghiệm') }}</p><h2>{{ data_get($serviceBlock, 'data.title', 'Dịch vụ nổi bật') }}</h2><span></span></header>
                <div class="foot-rail" data-foot-rail>
                    @forelse ($serviceItems as $item)
                        <article class="foot-service-card foot-rail__item">
                            <a href="{{ data_get($item, 'url', '#') }}">
                                <div class="foot-service-card__image"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><h3>{{ data_get($item, 'title', 'Trải nghiệm ẩm thực') }}</h3></div>
                                <div class="foot-service-card__copy">{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary', data_get($item, 'description', ''))), 150) }}</div>
                            </a>
                        </article>
                    @empty
                        <p class="foot-empty">Chưa có nội dung để hiển thị.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section id="gioi-thieu" class="foot-about" style="--foot-about-image: url('{{ data_get($aboutBlock, 'media.background', 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=2200&q=90') }}')">
            <div class="foot-container foot-about__panel">
                <img src="{{ $aboutImage }}" alt="{{ data_get($aboutBlock, 'data.title', 'FOOT401') }}">
                <div class="foot-about__copy"><p>{{ data_get($aboutBlock, 'data.subtitle', 'Câu chuyện của chúng tôi') }}</p><h2>{{ data_get($aboutBlock, 'data.title', 'Một căn bếp mở cho những cuộc gặp gỡ') }}</h2><div>{{ data_get($aboutBlock, 'data.description', 'Mỗi trải nghiệm tại FOOT401 bắt đầu từ sự trân trọng nguyên liệu và kết thúc bằng niềm vui được sẻ chia quanh bàn ăn.') }}</div>
                    @if (filled(data_get($aboutBlock, 'data.button_label')))
                        <a class="foot-button" href="{{ data_get($aboutBlock, 'settings.cta_url', '#dich-vu') }}">{{ data_get($aboutBlock, 'data.button_label') }}</a>
                    @endif
                </div>
            </div>
        </section>

        <section id="thuc-don" class="foot-menu-section">
            <div class="foot-menu-section__backdrop"></div>
            <div class="foot-container foot-menu-section__inner">
                <header class="foot-section-heading foot-section-heading--light"><p>{{ data_get($productBlock, 'data.subtitle', 'Từ bếp') }}</p><h2>{{ data_get($productBlock, 'data.title', 'Thực đơn theo mùa') }}</h2><span></span></header>
                <div class="foot-rail foot-rail--menu" data-foot-rail>
                    @forelse ($productItems as $item)
                        <article class="foot-menu-card foot-rail__item"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><h3>{{ data_get($item, 'title', 'Món ăn theo mùa') }}</h3><strong>{{ $formatPrice(data_get($item, 'price')) }}</strong><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary', '')), 120) }}</p></div></a></article>
                    @empty
                        <p class="foot-empty foot-empty--light">Thực đơn đang được cập nhật.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section id="tin-tuc" class="foot-section foot-section--paper">
            <div class="foot-container">
                <header class="foot-section-heading"><p>{{ data_get($newsBlock, 'data.subtitle', 'Nhật ký bàn ăn') }}</p><h2>{{ data_get($newsBlock, 'data.title', 'Tin tức và sự kiện') }}</h2><span></span></header>
                <div class="foot-rail" data-foot-rail>
                    @forelse ($newsItems as $item)
                        <article class="foot-story-card foot-rail__item"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><small>{{ data_get($item, 'published_at', data_get($item, 'meta', 'FOOT401')) }}</small><h3>{{ data_get($item, 'title', 'Câu chuyện từ căn bếp') }}</h3></div></a></article>
                    @empty
                        <p class="foot-empty">Chưa có nội dung để hiển thị.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section id="doi-ngu" class="foot-team-section">
            <div class="foot-container">
                <header class="foot-section-heading"><p>{{ data_get($teamBlock, 'data.subtitle', 'Những con người tạo nên trải nghiệm') }}</p><h2>{{ data_get($teamBlock, 'data.title', 'Đội ngũ của chúng tôi') }}</h2><span></span></header>
                <div class="foot-team-grid">
                    @forelse ($teamItems as $member)
                        <article class="foot-team-card"><img src="{{ data_get($member, 'image', 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=700&q=85') }}" alt="{{ data_get($member, 'name', data_get($member, 'title', 'Thành viên')) }}"><div><p>{{ data_get($member, 'role', data_get($member, 'position', 'Đầu bếp')) }}</p><h3>{{ data_get($member, 'name', data_get($member, 'title', 'Thành viên FOOT401')) }}</h3></div></article>
                    @empty
                        <p class="foot-empty">Đội ngũ đang được cập nhật.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </main>
@endsection
