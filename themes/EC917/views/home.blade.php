@extends('theme-ec917::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EGA Furniture')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $categories = $get('ec917_categories');
    $sale = $get('ec917_summer_sale');
    $promo = $get('ec917_promo_banner');
    $collections = $get('ec917_collections');
    $inspiration = $get('ec917_inspiration');
    $benefits = $get('ec917_benefits');
    $footer = $get('ec917_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $hotline = trim((string) data_get($branding, 'support_hotline')) ?: '1900 1993';
@endphp
<main class="ec17-main">
    <section class="ec17-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec17-slider" data-ec17-slider>
            @forelse($slides as $index => $slide)
                <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec17-slide style="--hero:url('{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec917/hero-interior.webp')) }}')">
                    <div class="ec17-hero-copy">
                        <span>{{ data_get($slide, 'badge', 'BLACK FRIDAY') }}</span>
                        <h1>{{ data_get($slide, 'title', 'Săn ngay deal nội thất khủng') }}</h1>
                        <p>{{ data_get($slide, 'summary', 'Giảm 50% tất cả sản phẩm') }}</p>
                        <a href="{{ data_get($slide, 'url', '#san-pham') }}">{{ data_get($slide, 'button_label', 'MUA NGAY') }}</a>
                    </div>
                </article>
            @empty
                <article class="is-active" data-ec17-slide style="--hero:url('/theme-demo/ec917/hero-interior.webp')"><div class="ec17-hero-copy"><span>BLACK FRIDAY</span><h1>Săn ngay deal nội thất khủng</h1><p>Giảm 50% tất cả sản phẩm</p><a href="#san-pham">MUA NGAY</a></div></article>
            @endforelse
            <button class="ec17-arrow prev" data-ec17-prev aria-label="Ảnh trước"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="ec17-arrow next" data-ec17-next aria-label="Ảnh sau"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="ec17-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec17-dot="{{ $index }}"></button>@endforeach</div>
        </div>
    </section>

    <section id="danh-muc" class="ec17-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec917_categories" data-ec17-reveal>
        <div class="ec17-container">
            <div class="ec17-heading"><h2>{{ data_get($categories, 'data.title', 'DANH MỤC SẢN PHẨM') }}</h2></div>
            <div class="ec17-category-grid">
                @foreach($items($categories) as $item)
                    <a href="{{ data_get($item, 'url', '#san-pham') }}" data-ec17-stagger><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary', data_get($item, 'count', '8').' sản phẩm') }}</p></a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="ec17-section ec17-sale xd-landing-block" data-landing-block-id="{{ data_get($sale, 'id') }}" data-block-type="ec917_summer_sale" data-ec17-reveal>
        <div class="ec17-container">
            <div class="ec17-sale-head"><h2>{{ data_get($sale, 'data.title', 'HAPPY SUMMER - GIẢM ĐẾN 50% 🔥') }}</h2><span>{{ data_get($sale, 'data.subtitle', 'Bán chạy') }}</span></div>
            <div class="ec17-product-grid">@foreach($items($sale) as $item)@include('theme-ec917::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>

    <section id="khuyen-mai" class="ec17-section ec17-promo xd-landing-block" data-landing-block-id="{{ data_get($promo, 'id') }}" data-block-type="ec917_promo_banner" data-ec17-reveal>
        <div class="ec17-container"><a href="{{ data_get($promo, 'data.url', '#san-pham') }}"><img src="{{ data_get($promo, 'media.image', '/theme-demo/ec917/promo-sofa.png') }}" alt="{{ data_get($promo, 'data.title', 'Sofa hàng mới về') }}"></a></div>
    </section>

    <section class="ec17-section xd-landing-block" data-landing-block-id="{{ data_get($collections, 'id') }}" data-block-type="ec917_collections" data-ec17-reveal>
        <div class="ec17-container">
            <div class="ec17-heading"><h2>{{ data_get($collections, 'data.title', 'BST NỘI THẤT DÀNH CHO BẠN') }}</h2></div>
            <div class="ec17-collection-grid">
                @foreach($items($collections) as $item)
                    <article data-ec17-stagger><a href="{{ data_get($item, 'url', '#') }}"><figure><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><i style="left:{{ data_get($item, 'x', 55) }}%;top:{{ data_get($item, 'y', 42) }}%">+</i><i style="left:{{ data_get($item, 'x2', 28) }}%;top:{{ data_get($item, 'y2', 65) }}%">+</i></figure><h3>{{ data_get($item, 'title') }}</h3><span>@themeT('EC917.view_detail', 'Xem chi tiết')</span></a></article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="cam-hung" class="ec17-section ec17-inspiration xd-landing-block" data-landing-block-id="{{ data_get($inspiration, 'id') }}" data-block-type="ec917_inspiration" data-ec17-reveal>
        <div class="ec17-container">
            <div class="ec17-heading ec17-heading-row"><h2>{{ data_get($inspiration, 'data.title', 'GÓC CẢM HỨNG') }}</h2><a href="{{ route('site.blog.index') }}">Xem tất cả</a></div>
            <div class="ec17-post-grid">
                @foreach($items($inspiration) as $item)
                    <article data-ec17-stagger><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"></a><h3>{{ data_get($item, 'title') }}</h3><small><i class="fa-solid fa-calendar-days"></i> {{ data_get($item, 'date', now()->format('d/m/Y')) }} <i class="fa-regular fa-clock"></i> {{ data_get($item, 'read_time', '2 phút đọc') }}</small><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><a href="{{ data_get($item, 'url', '#') }}">@themeT('EC917.read_more', 'Đọc tiếp')</a></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ec17-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec917_benefits" data-ec17-reveal>
        <div class="ec17-container">
            @foreach($items($benefits) as $index => $item)
                <article data-ec17-stagger><i class="fa-solid {{ data_get($item, 'icon', 'fa-gift') }}"></i><div><h3>{{ $index === 0 ? str_replace(['19001993', '1900 1993', '19006750', '0399162342'], $hotline, data_get($item, 'title')) : data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>
            @endforeach
        </div>
    </section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec917_footer"></section>
</main>
@endsection
