@extends('theme-ec912::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Sudes Phone')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $benefits = $get('ec912_benefits');
    $hotSale = $get('ec912_hot_sale');
    $categories = $get('ec912_featured_categories');
    $promotions = $get('ec912_promotion_banners');
    $iphones = $get('ec912_iphone_products');
    $news = $get('ec912_technology_news');
    $gallery = $get('ec912_customer_gallery');
    $footer = $get('ec912_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $dbHotline = trim((string) data_get($branding, 'support_hotline', '')) ?: '1900 6750';
@endphp
<main class="ec12-main">
    <section class="ec12-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec12-slider>
        @forelse($slides as $index => $slide)
            <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec12-slide>
                <img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec912/hero-tech.webp')) }}" alt="{{ data_get($slide, 'title', 'iPhone') }}">
                <div class="ec12-hero-copy"><span>{{ data_get($slide, 'badge', 'iPhone 14 Pro Max') }}</span><h1>{{ data_get($slide, 'title', 'GIÁ CỰC SỐC') }}</h1><p>{{ data_get($slide, 'summary', 'Giá chỉ từ 28.190.000đ') }}</p><a href="{{ data_get($slide, 'link_url', '#hot-sale') }}">Mua ngay</a></div>
            </article>
        @empty
            <article class="is-active" data-ec12-slide><img src="/theme-demo/ec912/hero-tech.webp" alt="iPhone chính hãng"><div class="ec12-hero-copy"><span>iPhone mới</span><h1>GIÁ CỰC SỐC</h1></div></article>
        @endforelse
        <div class="ec12-slider-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec12-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
    </section>

    <section class="ec12-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec912_benefits"><div class="ec12-container">
        @foreach($items($benefits) as $index => $item)
            <article class="tone-{{ ($index % 4) + 1 }}"><i class="fa-solid {{ data_get($item, 'icon', 'fa-truck-fast') }}"></i><div><h3>{{ $index === 3 ? 'Hotline: '.$dbHotline : data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>
        @endforeach
    </div></section>

    <section id="hot-sale" class="ec12-hot-sale xd-landing-block" data-landing-block-id="{{ data_get($hotSale, 'id') }}" data-block-type="ec912_hot_sale"><div class="ec12-container">
        <header><h2>{{ data_get($hotSale, 'data.title', 'HOT SALE CUỐI TUẦN 🔥') }}</h2><div class="ec12-countdown" data-ec12-countdown data-end="{{ data_get($hotSale, 'settings.end_at', now()->addDays(150)->toIso8601String()) }}"><span><b data-days>150</b>Ngày</span><span><b data-hours>02</b>Giờ</span><span><b data-minutes>21</b>Phút</span><span><b data-seconds>45</b>Giây</span></div></header>
        <div class="ec12-product-grid">@foreach($items($hotSale) as $item)@include('theme-ec912::partials.product-card', ['item' => $item, 'hot' => true])@endforeach</div>
        <a class="ec12-more ec12-more-light" href="{{ route('site.catalog.search') }}">@themeT('EC912.view_all', 'Xem tất cả') <i class="fa-solid fa-chevron-right"></i></a>
    </div></section>

    <section class="ec12-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec912_featured_categories"><div class="ec12-container">
        <h2 class="ec12-heading">{{ data_get($categories, 'data.title', 'DANH MỤC NỔI BẬT') }}</h2>
        <div class="ec12-category-grid">@foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#iphone') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec912/phone-blue.webp')) }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div>
    </div></section>

    <section class="ec12-promotions xd-landing-block" data-landing-block-id="{{ data_get($promotions, 'id') }}" data-block-type="ec912_promotion_banners"><div class="ec12-container">
        @foreach($items($promotions) as $item)<a href="{{ data_get($item, 'url', '#iphone') }}"><img src="{{ data_get($item, 'image', '/theme-demo/ec912/promo-phone.webp') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach
    </div></section>

    <section id="iphone" class="ec12-section ec12-iphone xd-landing-block" data-landing-block-id="{{ data_get($iphones, 'id') }}" data-block-type="ec912_iphone_products"><div class="ec12-container">
        <h2 class="ec12-heading">{{ data_get($iphones, 'data.title', 'IPHONE') }}</h2>
        <div class="ec12-product-grid">@foreach($items($iphones) as $item)@include('theme-ec912::partials.product-card', ['item' => $item])@endforeach</div>
    </div></section>

    <section id="tin-tuc" class="ec12-section ec12-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec912_technology_news"><div class="ec12-container">
        <h2 class="ec12-heading">{{ data_get($news, 'data.title', 'TIN TỨC') }} <span>CÔNG NGHỆ</span></h2>
        <div class="ec12-news-grid">@foreach($items($news) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec912/story-phone.webp')) }}" alt="{{ data_get($item, 'title') }}"></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><small><i class="fa-solid fa-clock"></i> {{ data_get($item, 'date', '28/04/2026') }}</small></article>@endforeach</div>
    </div></section>

    <section class="ec12-section ec12-gallery xd-landing-block" data-landing-block-id="{{ data_get($gallery, 'id') }}" data-block-type="ec912_customer_gallery"><div class="ec12-container">
        <h2 class="ec12-heading">{{ data_get($gallery, 'data.title', 'KHÁCH HÀNG CỦA') }} <span>{{ data_get($siteProfile ?? [], 'site_name', 'SUDES') }}</span></h2>
        <div class="ec12-gallery-grid">@foreach($items($gallery) as $item)<figure><img src="{{ data_get($item, 'image', '/theme-demo/ec912/story-review.webp') }}" alt="{{ data_get($item, 'title', 'Khách hàng') }}"><figcaption>{{ data_get($item, 'title') }}</figcaption></figure>@endforeach</div>
    </div></section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec912_footer"></section>
</main>
@endsection
