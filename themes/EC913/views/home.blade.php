@extends('theme-ec913::layout')

@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'NovaTech Mall')))

@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();

        return $dynamic->isNotEmpty()
            ? $dynamic
            : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };

    $hero = $get('hero_slider');
    $benefits = $get('ec913_benefits');
    $categories = $get('ec913_category_grid');
    $promotions = $get('ec913_promotion_banners');
    $bestSellers = $get('ec913_best_sellers');
    $laptops = $get('ec913_laptop_showcase');
    $news = $get('ec913_technology_news');
    $footer = $get('ec913_footer');

    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }

    $promotionItems = $items($promotions);
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $hotline = trim((string) data_get($branding, 'support_hotline', '')) ?: '0399162342';
@endphp

<main class="ec13-main">
    <section class="ec13-hero-shell xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec13-container ec13-hero-layout">
            <aside class="ec13-category-menu" data-ec13-category-menu>
                <h2><i class="fa-solid fa-bars-staggered"></i> @themeT('EC913.categories', 'Danh mục sản phẩm')</h2>
                @foreach($items($categories)->take(9) as $index => $category)
                    <a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ data_get($category, 'url', route('site.catalog.search')) }}">
                        <span><i class="fa-solid {{ ['fa-mobile-screen-button','fa-laptop','fa-tv','fa-snowflake','fa-house-signal','fa-headphones','fa-gamepad','fa-camera','fa-plug'][$index] ?? 'fa-microchip' }}"></i>{{ data_get($category, 'title', data_get($category, 'name')) }}</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                @endforeach
            </aside>

            <div class="ec13-hero-slider" data-ec13-slider>
                @forelse($slides as $index => $slide)
                    <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec13-slide>
                        <img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec913/hero-digital-mall.webp')) }}" alt="{{ data_get($slide, 'title', 'Công nghệ cho mọi nhà') }}">
                        <div class="ec13-hero-copy">
                            <span>{{ data_get($slide, 'badge', 'ĐẠI TIỆC CÔNG NGHỆ') }}</span>
                            <h1>{{ data_get($slide, 'title', 'Sắm công nghệ, sống tiện nghi') }}</h1>
                            <p>{{ data_get($slide, 'summary', 'Ưu đãi đến 35% cho điện tử, điện máy và phụ kiện chính hãng.') }}</p>
                            <a href="{{ data_get($slide, 'link_url', '#ban-chay') }}">@themeT('EC913.shop_now', 'Mua ngay') <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                @empty
                    <article class="is-active" data-ec13-slide>
                        <img src="/theme-demo/ec913/hero-digital-mall.webp" alt="Công nghệ cho mọi nhà">
                        <div class="ec13-hero-copy"><span>ĐẠI TIỆC CÔNG NGHỆ</span><h1>Sắm công nghệ, sống tiện nghi</h1><p>Ưu đãi đến 35% cho hàng ngàn sản phẩm chính hãng.</p><a href="#ban-chay">Mua ngay</a></div>
                    </article>
                @endforelse
                <div class="ec13-slider-dots">
                    @foreach($slides as $index => $slide)
                        <button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec13-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            </div>

            <div class="ec13-side-promos">
                @foreach($promotionItems->take(2) as $index => $item)
                    <a href="{{ data_get($item, 'url', '#ban-chay') }}" class="tone-{{ $index + 1 }}">
                        <img src="{{ data_get($item, 'image', $index === 0 ? '/theme-demo/ec913/promo-gaming.webp' : '/theme-demo/ec913/promo-appliances.webp') }}" alt="{{ data_get($item, 'title') }}">
                        <span><small>{{ data_get($item, 'badge', $index === 0 ? 'GIẢI TRÍ ĐỈNH CAO' : 'NHÀ THÔNG MINH') }}</small><b>{{ data_get($item, 'title') }}</b><em>{{ data_get($item, 'summary') }}</em></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ec13-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec913_benefits">
        <div class="ec13-container">
            @foreach($items($benefits) as $index => $item)
                <article>
                    <i class="fa-solid {{ data_get($item, 'icon', 'fa-truck-fast') }}"></i>
                    <div><h3>{{ $index === 3 ? 'Hotline '.$hotline : data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="ec13-section ec13-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec913_category_grid">
        <div class="ec13-container">
            <div class="ec13-section-head"><div><span>Khám phá nhanh</span><h2>{{ data_get($categories, 'data.title', 'Danh mục nổi bật') }}</h2></div><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a></div>
            <div class="ec13-category-grid">
                @foreach($items($categories) as $item)
                    <a href="{{ data_get($item, 'url', route('site.catalog.search')) }}">
                        <span><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec913/phone-blue.webp')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></span>
                        <b>{{ data_get($item, 'title', data_get($item, 'name')) }}</b>
                        <small>{{ data_get($item, 'summary', 'Nhiều lựa chọn') }}</small>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ec13-banner-strip xd-landing-block" data-landing-block-id="{{ data_get($promotions, 'id') }}" data-block-type="ec913_promotion_banners">
        <div class="ec13-container">
            @foreach($promotionItems->take(2) as $index => $item)
                <a href="{{ data_get($item, 'url', '#ban-chay') }}">
                    <img src="{{ data_get($item, 'image', $index === 0 ? '/theme-demo/ec913/promo-appliances.webp' : '/theme-demo/ec913/promo-gaming.webp') }}" alt="{{ data_get($item, 'title') }}">
                    <span><small>{{ data_get($item, 'badge', 'ƯU ĐÃI ĐẶC BIỆT') }}</small><b>{{ data_get($item, 'title') }}</b><em>{{ data_get($item, 'summary') }}</em><strong>Xem ngay <i class="fa-solid fa-arrow-right"></i></strong></span>
                </a>
            @endforeach
        </div>
    </section>

    <section id="ban-chay" class="ec13-section ec13-products xd-landing-block" data-landing-block-id="{{ data_get($bestSellers, 'id') }}" data-block-type="ec913_best_sellers">
        <div class="ec13-container">
            <div class="ec13-section-head"><div><span>Được chọn nhiều nhất</span><h2>{{ data_get($bestSellers, 'data.title', 'Sản phẩm bán chạy') }}</h2></div><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a></div>
            <div class="ec13-product-grid">
                @foreach($items($bestSellers) as $item)
                    @include('theme-ec913::partials.product-card', ['item' => $item])
                @endforeach
            </div>
        </div>
    </section>

    <section id="laptop" class="ec13-section ec13-laptops xd-landing-block" data-landing-block-id="{{ data_get($laptops, 'id') }}" data-block-type="ec913_laptop_showcase">
        <div class="ec13-container">
            <div class="ec13-section-head"><div><span>Hiệu năng bứt phá</span><h2>{{ data_get($laptops, 'data.title', 'Laptop & Thiết bị tin học') }}</h2></div><a href="{{ route('site.catalog.search', ['q' => 'laptop']) }}">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a></div>
            <div class="ec13-laptop-layout">
                @php($laptopItems = $items($laptops))
                @if($featuredLaptop = $laptopItems->first())
                    <article class="ec13-featured-laptop">
                        <img src="{{ data_get($featuredLaptop, 'image', data_get($featuredLaptop, 'image_url', '/theme-demo/ec913/laptop-silver.webp')) }}" alt="{{ data_get($featuredLaptop, 'title', data_get($featuredLaptop, 'name')) }}">
                        <div><span>Giảm đến 18%</span><h3>{{ data_get($featuredLaptop, 'title', data_get($featuredLaptop, 'name')) }}</h3><p>{{ data_get($featuredLaptop, 'summary', 'Màn hình sắc nét, hiệu năng mạnh mẽ, thiết kế mỏng nhẹ.') }}</p><strong>{{ number_format((int) data_get($featuredLaptop, 'price', 0), 0, ',', '.') }}đ</strong><a href="{{ data_get($featuredLaptop, 'url', '#') }}">Xem chi tiết</a></div>
                    </article>
                @endif
                <div class="ec13-laptop-grid">
                    @foreach($laptopItems->skip(1)->take(4) as $item)
                        @include('theme-ec913::partials.product-card', ['item' => $item, 'compact' => true])
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="ec13-section ec13-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec913_technology_news">
        <div class="ec13-container">
            <div class="ec13-section-head"><div><span>Góc công nghệ</span><h2>{{ data_get($news, 'data.title', 'Tin mới & tư vấn') }}</h2></div><a href="{{ route('site.blog.index') }}">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a></div>
            <div class="ec13-news-grid">
                @foreach($items($news) as $item)
                    <article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec913/promo-gaming.webp')) }}" alt="{{ data_get($item, 'title') }}"></a><div><span>Tư vấn công nghệ</span><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><a href="{{ data_get($item, 'url', '#') }}">Đọc thêm <i class="fa-solid fa-arrow-right"></i></a></div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec913_footer"></section>
</main>
@endsection
