@extends('theme-ec901::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC901')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $hero = $get('hero_slider');
    $flash = $get('ec901_flash_deals');
    $categories = $get('ec901_featured_categories');
    $popular = $get('ec901_best_sellers');
    $gender = $get('ec901_gender_banners');
    $promo = $get('ec901_promotion_mosaic');
    $grid = $get('ec901_product_grid');
    $mini = $get('ec901_mini_promotions');
    $luxury = $get('ec901_luxury_collection');
    $reviews = $get('ec901_testimonials');
    $brands = $get('ec901_featured_brands');
    $news = $get('ec901_latest_posts');
    $benefits = $get('ec901_benefits');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec91-main">
    <section class="ec91-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec91-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5600) }}">
        @forelse($slides as $index => $slide)
            <article class="ec91-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-ec91-slide>
                <img src="{{ $image($slide, '/theme-demo/ec901/hero-watches.webp') }}" alt="{{ data_get($slide, 'title') }}">
                <div class="ec91-hero-copy"><small>{{ data_get($slide, 'badge', 'BỘ SƯU TẬP MỚI') }}</small><h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1><p>{{ data_get($slide, 'summary', data_get($slide, 'subtitle', data_get($hero, 'data.description'))) }}</p><a href="{{ data_get($slide, 'link_url', '#deal-chop-nhoang') }}">{{ data_get($slide, 'button_label', 'Khám phá ngay') }} <i class="fa-solid fa-arrow-right"></i></a></div>
            </article>
        @empty
            <article class="ec91-hero-slide is-active" data-ec91-slide><img src="/theme-demo/ec901/hero-watches.webp" alt="Bộ sưu tập đồng hồ"></article>
        @endforelse
        <button class="ec91-arrow ec91-prev" data-ec91-prev aria-label="Slide trước"><i class="fa-solid fa-arrow-left"></i></button>
        <button class="ec91-arrow ec91-next" data-ec91-next aria-label="Slide sau"><i class="fa-solid fa-arrow-right"></i></button>
    </section>

    <section id="{{ data_get($flash, 'anchor_id', 'deal-chop-nhoang') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($flash, 'id') }}" data-block-type="ec901_flash_deals">
        <div class="ec91-container ec91-flash">
            <header><h2>{{ data_get($flash, 'data.title', 'Deal chớp nhoáng') }}</h2><p>Kết thúc sau: <time data-ec91-countdown="{{ data_get($flash, 'settings.ends_at') }}">00:00:00</time></p></header>
            <div class="ec91-carousel" data-ec91-scroll>@foreach($items($flash) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>

    <section id="{{ data_get($categories, 'anchor_id', 'danh-muc') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec901_featured_categories">
        <div class="ec91-container"><h2 class="ec91-center">{{ data_get($categories, 'data.title', 'Danh mục nổi bật') }}</h2><div class="ec91-categories">@foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec901/watch-men.webp') }}" alt="{{ data_get($item, 'title') }}"><b>{{ data_get($item, 'title') }}</b></a>@endforeach</div></div>
    </section>

    <section id="{{ data_get($popular, 'anchor_id', 'ban-chay') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($popular, 'id') }}" data-block-type="ec901_best_sellers">
        <div class="ec91-container"><header class="ec91-heading"><h2>{{ data_get($popular, 'data.title', 'Sản phẩm bán chạy') }}</h2><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right-long"></i></a></header><div class="ec91-carousel" data-ec91-scroll>@foreach($items($popular) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section id="{{ data_get($gender, 'anchor_id', 'phong-cach') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($gender, 'id') }}" data-block-type="ec901_gender_banners">
        <div class="ec91-container ec91-gender">@foreach($items($gender) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec901/watch-men.webp') }}" alt="{{ data_get($item, 'title') }}"><span><small>Xem tất cả <i class="fa-solid fa-arrow-right-long"></i></small><b>{{ data_get($item, 'title') }}</b></span></a>@endforeach</div>
    </section>

    <section id="{{ data_get($promo, 'anchor_id', 'khuyen-mai') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($promo, 'id') }}" data-block-type="ec901_promotion_mosaic">
        <div class="ec91-container"><h2 class="ec91-center">{{ data_get($promo, 'data.title', 'Khuyến mãi nổi bật') }}</h2><div class="ec91-promo">@foreach($items($promo) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec901/promo-main.webp') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach</div></div>
    </section>

    <section id="{{ data_get($grid, 'anchor_id', 'san-pham') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($grid, 'id') }}" data-block-type="ec901_product_grid">
        <div class="ec91-container"><h2 class="ec91-center">{{ data_get($grid, 'data.title', 'Bán chạy nhất') }}</h2><div class="ec91-product-grid">@foreach($items($grid) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section id="{{ data_get($mini, 'anchor_id', 'uu-dai-nho') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($mini, 'id') }}" data-block-type="ec901_mini_promotions">
        <div class="ec91-container ec91-mini-promos">@foreach($items($mini) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec901/promo-gold.webp') }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div>
    </section>

    <section id="{{ data_get($luxury, 'anchor_id', 'dong-ho-cao-cap') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($luxury, 'id') }}" data-block-type="ec901_luxury_collection">
        <div class="ec91-container"><header class="ec91-heading"><h2>{{ data_get($luxury, 'data.title', 'Đồng hồ cao cấp') }}</h2><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right-long"></i></a></header><div class="ec91-luxury"><img src="{{ data_get($luxury, 'settings.feature_image', '/theme-demo/ec901/promo-main.webp') }}" alt=""><div class="ec91-carousel" data-ec91-scroll>@foreach($items($luxury) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@endforeach</div></div></div>
    </section>

    <section id="{{ data_get($reviews, 'anchor_id', 'danh-gia') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($reviews, 'id') }}" data-block-type="ec901_testimonials">
        <div class="ec91-container"><h2>{{ data_get($reviews, 'data.title', 'Đánh giá từ khách hàng') }}</h2><div class="ec91-reviews">@foreach($items($reviews) as $item)<article><img src="{{ $image($item, '/theme-demo/ec901/watch-women.webp') }}" alt=""><h3>{{ data_get($item, 'title') }}</h3><b>★★★★★</b><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div></div>
    </section>

    <section id="{{ data_get($brands, 'anchor_id', 'thuong-hieu') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec901_featured_brands">
        <div class="ec91-container"><h2 class="ec91-center">{{ data_get($brands, 'data.title', 'Thương hiệu nổi bật') }}</h2><div class="ec91-brands">@foreach($items($brands) as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a>@endforeach</div></div>
    </section>

    <section id="{{ data_get($news, 'anchor_id', 'tin-moi') }}" class="ec91-section xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec901_latest_posts">
        <div class="ec91-container"><header class="ec91-heading"><h2>{{ data_get($news, 'data.title', 'Tin mới cập nhật') }}</h2><a href="{{ route('site.blog.index') }}">Xem tất cả <i class="fa-solid fa-arrow-right-long"></i></a></header><div class="ec91-news">@foreach($items($news) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec901/watch-men.webp') }}" alt="{{ data_get($item, 'title') }}"></a><time>{{ data_get($item, 'published_at', now()->format('d/m/Y')) }}</time><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3></article>@endforeach</div></div>
    </section>

    <section id="{{ data_get($benefits, 'anchor_id', 'cam-ket') }}" class="ec91-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec901_benefits">
        <div class="ec91-container">@foreach($items($benefits) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-regular fa-clock') }}"></i><b>{{ data_get($item, 'title') }}</b></article>@endforeach</div>
    </section>
</main>
@endsection
