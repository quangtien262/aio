@extends('theme-ec904::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC904')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $categories = $get('ec904_category_carousel');
    $sale = $get('ec904_tabbed_sale');
    $technology = $get('ec904_technology_products');
    $fashion = $get('ec904_fashion_products');
    $suggestions = $get('ec904_daily_suggestions');
    $latestPosts = $get('ec904_latest_posts');
    $newsletter = $get('ec904_newsletter');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec94-main">
    <section class="ec94-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec94-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5200) }}">
        @forelse($slides as $index => $slide)<article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec94-slide><img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec904/hero-super-sale.webp')) }}" alt="{{ data_get($slide, 'title') }}"><div class="ec94-hero-copy"><small>{{ data_get($slide, 'badge', 'THIÊN ĐƯỜNG MUA SẮM') }}</small><h1>{{ data_get($slide, 'title', data_get($hero, 'data.title', 'Siêu sale đa ngành')) }}</h1><p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p><a class="ec94-button" href="{{ data_get($slide, 'link_url', '#dien-thoai') }}">{{ data_get($slide, 'button_label', 'Mua ngay') }}</a></div></article>@empty<article class="is-active" data-ec94-slide><img src="/theme-demo/ec904/hero-super-sale.webp" alt="Siêu sale"></article>@endforelse
        <button class="ec94-hero-nav ec94-hero-prev" data-ec94-prev><i class="fa-solid fa-angle-left"></i></button><button class="ec94-hero-nav ec94-hero-next" data-ec94-next><i class="fa-solid fa-angle-right"></i></button>
    </section>

    <section id="danh-muc" class="ec94-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec904_category_carousel"><div class="ec94-container ec94-category-row">@foreach($items($categories) as $item)<a class="ec94-category-item" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec904/phone-front.webp')) }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div></section>

    <section id="dien-thoai" class="ec94-section xd-landing-block" data-landing-block-id="{{ data_get($sale, 'id') }}" data-block-type="ec904_tabbed_sale"><div class="ec94-container">
        <header class="ec94-section-head"><h2>{{ data_get($sale, 'data.title', 'Điện thoại') }}</h2><nav><span>ĐIỆN THOẠI</span><span>THỜI TRANG</span><span>GIA DỤNG</span></nav></header>
        <div class="ec94-sale-tabs"><a class="ec94-promo" href="#do-cong-nghe"><img src="{{ data_get($sale, 'data.content.promo_image', '/theme-demo/ec904/promo-home.webp') }}" alt="Khuyến mãi điện thoại"></a><div class="ec94-product-strip">@foreach($items($sale) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@endforeach</div></div>
    </div></section>

    <section id="do-cong-nghe" class="ec94-section xd-landing-block" data-landing-block-id="{{ data_get($technology, 'id') }}" data-block-type="ec904_technology_products"><div class="ec94-container">
        <header class="ec94-section-head"><h2>{{ data_get($technology, 'data.title', 'Đồ công nghệ') }}</h2><nav><span>Điện thoại - Máy tính bảng</span><span>Phụ kiện - Thiết bị số</span><span>Laptop - Thiết bị IT</span></nav></header>
        <div class="ec94-catalog-layout"><a class="ec94-promo" href="#"><img src="{{ data_get($technology, 'data.content.promo_image', '/theme-demo/ec904/promo-tech.webp') }}" alt="Đồ công nghệ"></a><div class="ec94-product-grid">@foreach($items($technology) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@endforeach</div></div>
    </div></section>

    <section id="thoi-trang" class="ec94-section xd-landing-block" data-landing-block-id="{{ data_get($fashion, 'id') }}" data-block-type="ec904_fashion_products"><div class="ec94-container">
        <header class="ec94-section-head"><h2>{{ data_get($fashion, 'data.title', 'Thời trang') }}</h2><nav><span>Thời trang nữ</span><span>Thời trang nam</span><span>Đồng hồ & Trang sức</span><span>Giày dép</span></nav></header>
        <div class="ec94-fashion-layout"><a class="ec94-promo" href="#"><img src="{{ data_get($fashion, 'data.content.promo_image', '/theme-demo/ec904/promo-fashion.webp') }}" alt="Thời trang"></a><div class="ec94-product-grid">@foreach($items($fashion) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@endforeach</div></div>
    </div></section>

    <section id="goi-y" class="ec94-section xd-landing-block" data-landing-block-id="{{ data_get($suggestions, 'id') }}" data-block-type="ec904_daily_suggestions"><div class="ec94-container"><header class="ec94-section-head"><h2>{{ data_get($suggestions, 'data.title', 'Gợi ý hôm nay') }}</h2></header><div class="ec94-suggestions"><div class="ec94-product-strip">@foreach($items($suggestions) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@endforeach</div></div></div></section>

    <section id="tin-tuc" class="ec94-section xd-landing-block" data-landing-block-id="{{ data_get($latestPosts, 'id') }}" data-block-type="ec904_latest_posts"><div class="ec94-container"><header class="ec94-section-head"><h2>{{ data_get($latestPosts, 'data.title', 'Tin tức mới nhất') }}</h2></header><div class="ec94-news-grid">@foreach($items($latestPosts) as $index => $item)<article class="ec94-news-card"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec904/news-foldable.webp')) }}" alt="{{ data_get($item, 'title') }}"></a><b>{{ ['Tin tức','Đời sống','Khuyến mãi','Sự kiện'][$index % 4] }}</b><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p></article>@endforeach</div></div></section>
    <section class="xd-landing-block" hidden data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec904_newsletter"></section>
</main>
@endsection
