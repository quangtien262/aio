@extends('theme-ec902::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC902')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $hero = $get('hero_slider');
    $benefits = $get('ec902_benefits');
    $categories = $get('ec902_featured_categories');
    $tabs = $get('ec902_product_tabs');
    $deals = $get('ec902_featured_deals');
    $phones = $get('ec902_phone_collection');
    $tablets = $get('ec902_tablet_collection');
    $accessoryCategories = $get('ec902_accessory_categories');
    $accessories = $get('ec902_accessory_products');
    $banner = $get('ec902_wide_banner');
    $news = $get('ec902_latest_posts');
    $videos = $get('ec902_video_reviews');
    $reviews = $get('ec902_testimonials');
    $support = $get('ec902_support_strip');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $heroPromos = collect(data_get($hero, 'data.content.promos', []))->filter()->values();
@endphp
<main class="ec92-main">
    <section class="ec92-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec92-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5600) }}">
        <div class="ec92-hero-main">@forelse($slides as $index => $slide)<article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec92-slide><img src="{{ $image($slide, '/theme-demo/ec902/hero-tech.webp') }}" alt="{{ data_get($slide, 'title') }}"><div><small>{{ data_get($slide, 'kicker', data_get($slide, 'badge', 'NOVA SIGNATURE')) }}</small><h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1><p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p><a href="{{ data_get($slide, 'link_url', '#san-pham-moi') }}">{{ data_get($slide, 'button_label', 'Mua ngay') }} <i class="fa-solid fa-arrow-right"></i></a></div></article>@empty<article class="is-active" data-ec92-slide><img src="/theme-demo/ec902/hero-tech.webp" alt="Thiết bị công nghệ"></article>@endforelse<button class="ec92-arrow ec92-prev" data-ec92-prev><i class="fa-solid fa-chevron-left"></i></button><button class="ec92-arrow ec92-next" data-ec92-next><i class="fa-solid fa-chevron-right"></i></button></div>
        <div class="ec92-container ec92-hero-promos">@foreach($heroPromos as $item)<a href="{{ data_get($item, 'url', '#san-pham-moi') }}"><img src="{{ $image($item, '/theme-demo/ec902/promo-phone.webp') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach</div>
    </section>

    <section class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec902_benefits"><div class="ec92-container ec92-benefits">@foreach($items($benefits) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-truck-fast') }}"></i><b>{{ data_get($item, 'title') }}</b></article>@endforeach</div></section>

    <section id="{{ data_get($categories, 'anchor_id', 'danh-muc') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec902_featured_categories"><div class="ec92-container ec92-panel"><h2>{{ data_get($categories, 'data.title', 'Danh mục nổi bật') }}</h2><div class="ec92-categories">@foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec902/phone-blue.webp') }}" alt="{{ data_get($item, 'title') }}"><div><b>{{ data_get($item, 'title') }}</b><span>{{ data_get($item, 'summary', data_get($item, 'count_label', 'Nhiều ưu đãi hấp dẫn')) }}</span></div><i class="fa-solid fa-chevron-right"></i></a>@endforeach</div></div></section>

    <section id="{{ data_get($tabs, 'anchor_id', 'san-pham-moi') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($tabs, 'id') }}" data-block-type="ec902_product_tabs"><div class="ec92-container ec92-panel"><div class="ec92-tabs"><span><i class="fa-solid fa-certificate"></i> Sản phẩm mới</span><span><i class="fa-solid fa-fire"></i> Sản phẩm nổi bật</span><span><i class="fa-solid fa-medal"></i> Sản phẩm bán chạy</span></div><div class="ec92-product-row">@foreach($items($tabs) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@endforeach</div></div></section>

    <section id="{{ data_get($deals, 'anchor_id', 'deal-noi-bat') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($deals, 'id') }}" data-block-type="ec902_featured_deals"><div class="ec92-container ec92-deals"><header><h2><i class="fa-solid fa-fire-flame-curved"></i> {{ data_get($deals, 'data.title', 'Deal nổi bật') }}</h2><p>{{ data_get($deals, 'data.description', 'Sản phẩm chính hãng, bảo hành tận tâm') }}</p></header><div class="ec92-deal-content"><a class="ec92-deal-banner" href="#dien-thoai"><img src="{{ data_get($deals, 'settings.feature_image', '/theme-demo/ec902/promo-phone.webp') }}" alt="Deal công nghệ"><span>GIẢM ĐẾN <b>5 TRIỆU</b></span></a><div class="ec92-product-row">@foreach($items($deals) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@endforeach</div></div><a class="ec92-more" href="{{ route('site.catalog.search') }}">Xem toàn bộ sản phẩm <i class="fa-solid fa-arrow-right"></i></a></div></section>

    <section id="{{ data_get($phones, 'anchor_id', 'dien-thoai') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($phones, 'id') }}" data-block-type="ec902_phone_collection"><div class="ec92-container ec92-split"><aside><img src="{{ data_get($phones, 'settings.feature_image', '/theme-demo/ec902/promo-phone.webp') }}" alt="Smartphone"><b>Điện thoại thế hệ mới</b></aside><div class="ec92-panel"><h2>{{ data_get($phones, 'data.title', 'Smartphone') }}</h2><div class="ec92-product-row">@foreach($items($phones) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@endforeach</div></div></div></section>

    <section id="{{ data_get($tablets, 'anchor_id', 'may-tinh-bang') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($tablets, 'id') }}" data-block-type="ec902_tablet_collection"><div class="ec92-container ec92-split ec92-split-reverse"><div class="ec92-panel"><h2>{{ data_get($tablets, 'data.title', 'Máy tính bảng') }}</h2><div class="ec92-product-row">@foreach($items($tablets) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@endforeach</div></div><aside><img src="{{ data_get($tablets, 'settings.feature_image', '/theme-demo/ec902/promo-computing.webp') }}" alt="Máy tính bảng"><b>Công nghệ cho sáng tạo</b></aside></div></section>

    <section id="{{ data_get($accessoryCategories, 'anchor_id', 'phu-kien-noi-bat') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($accessoryCategories, 'id') }}" data-block-type="ec902_accessory_categories"><div class="ec92-container ec92-panel"><h2>{{ data_get($accessoryCategories, 'data.title', 'Phụ kiện nổi bật') }}</h2><div class="ec92-accessory-categories">@foreach($items($accessoryCategories) as $item)<a href="{{ data_get($item, 'url', '#phu-kien') }}"><img src="{{ $image($item, '/theme-demo/ec902/charger-wall.webp') }}" alt="{{ data_get($item, 'title') }}"><b>{{ data_get($item, 'title') }}</b></a>@endforeach</div></div></section>

    <section id="{{ data_get($accessories, 'anchor_id', 'phu-kien') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($accessories, 'id') }}" data-block-type="ec902_accessory_products"><div class="ec92-container ec92-panel"><h2>{{ data_get($accessories, 'data.title', 'Phụ kiện') }}</h2><div class="ec92-product-row">@foreach($items($accessories) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@endforeach</div></div></section>

    <section class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($banner, 'id') }}" data-block-type="ec902_wide_banner"><div class="ec92-container">@foreach($items($banner) as $item)<a class="ec92-wide-banner" href="{{ data_get($item, 'url', '#may-tinh-bang') }}"><img src="{{ $image($item, '/theme-demo/ec902/promo-computing.webp') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach</div></section>

    <section id="{{ data_get($news, 'anchor_id', 'tin-tuc') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec902_latest_posts"><div class="ec92-container ec92-panel"><h2>{{ data_get($news, 'data.title', 'Tin tức mới nhất') }}</h2><div class="ec92-news">@foreach($items($news) as $index => $item)<article class="{{ $index === 0 ? 'is-featured' : '' }}"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec902/story-phone.webp') }}" alt="{{ data_get($item, 'title') }}"></a><div><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach</div></div></section>

    <section id="{{ data_get($videos, 'anchor_id', 'video-review') }}" class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($videos, 'id') }}" data-block-type="ec902_video_reviews"><div class="ec92-container ec92-panel"><h2>{{ data_get($videos, 'data.title', 'Xem nhiều nhất') }}</h2><div class="ec92-videos">@foreach($items($videos) as $item)<a href="{{ data_get($item, 'url', '#') }}"><span><img src="{{ $image($item, '/theme-demo/ec902/story-review.webp') }}" alt="{{ data_get($item, 'title') }}"><i class="fa-solid fa-play"></i></span><b>{{ data_get($item, 'title') }}</b></a>@endforeach</div></div></section>

    <section class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($reviews, 'id') }}" data-block-type="ec902_testimonials"><div class="ec92-container ec92-panel"><h2>{{ data_get($reviews, 'data.title', 'Feedback từ khách hàng') }}</h2><div class="ec92-reviews">@foreach($items($reviews) as $item)<article><img src="{{ $image($item, '/theme-demo/ec902/story-review.webp') }}" alt=""><div><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'role') }}</small></div><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div></div></section>

    <section class="ec92-section xd-landing-block" data-landing-block-id="{{ data_get($support, 'id') }}" data-block-type="ec902_support_strip"><div class="ec92-container ec92-support">@foreach($items($support) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-shield-halved') }}"></i><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></article>@endforeach</div></section>
</main>
@endsection
