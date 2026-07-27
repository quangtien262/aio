@extends('theme-ec906::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EGA Mini Mart')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $benefits = $get('ec906_benefits');
    $flash = $get('ec906_flash_sale');
    $family = $get('ec906_family_care');
    $promos = $get('ec906_category_promos');
    $kitchen = $get('ec906_kitchen_products');
    $news = $get('ec906_latest_posts');
    $brands = $get('ec906_brand_strip');
    $newsletter = $get('ec906_newsletter');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec96-main">
    <section class="ec96-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec96-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5500) }}">
        @forelse($slides as $index => $slide)
            <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec96-slide style="--hero-image:url('{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec906/hero-minimart.png')) }}')">
                <div class="ec96-container ec96-hero-copy">
                    <p>{{ data_get($slide, 'badge', 'DUY NHẤT TẠI EGA MINI MART') }}</p>
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title', 'ĐẠI TIỆC KHUYẾN MÃI')) }}</h1>
                    <strong>{{ data_get($slide, 'summary', data_get($hero, 'data.description', 'Giảm giá lên đến 65%')) }}</strong>
                    <small>Từ ngày 01.05 – 05.05</small>
                    <a href="{{ data_get($slide, 'link_url', '#flash-sale') }}">{{ data_get($slide, 'button_label', 'Mua ngay') }}</a>
                </div>
            </article>
        @empty
            <article class="is-active" data-ec96-slide style="--hero-image:url('/theme-demo/ec906/hero-minimart.png')"><div class="ec96-container ec96-hero-copy"><p>DUY NHẤT TẠI EGA MINI MART</p><h1>ĐẠI TIỆC KHUYẾN MÃI</h1><strong>Giảm giá lên đến 65%</strong><small>Từ ngày 01.05 – 05.05</small></div></article>
        @endforelse
        <div class="ec96-hero-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec96-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
    </section>

    <section id="gioi-thieu" class="ec96-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec906_benefits"><div class="ec96-container">
        @foreach($items($benefits) as $item)<article><i class="fa-solid {{ data_get($item, 'icon', 'fa-truck-fast') }}"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach
    </div></section>

    <div class="ec96-soft-bg">
        <section id="flash-sale" class="ec96-section ec96-flash xd-landing-block" data-landing-block-id="{{ data_get($flash, 'id') }}" data-block-type="ec906_flash_sale"><div class="ec96-container">
            <header><h2>{{ data_get($flash, 'data.title', 'Chớp thời cơ. Giá như mơ!') }}</h2><div><span>Nhanh lên nào!<b>Sự kiện sẽ kết thúc sau</b></span><time data-ec96-countdown data-hours="4"><b>04<small>Giờ</small></b><b>33<small>Phút</small></b><b>09<small>Giây</small></b></time></div></header>
            <div class="ec96-product-row">@foreach($items($flash) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@endforeach</div>
        </div></section>

        <section id="cham-soc-gia-dinh" class="ec96-section xd-landing-block" data-landing-block-id="{{ data_get($family, 'id') }}" data-block-type="ec906_family_care"><div class="ec96-container">
            <div class="ec96-title"><span></span><h2>{{ data_get($family, 'data.title', 'Chăm sóc gia đình') }}</h2><span></span></div>
            <div class="ec96-product-row ec96-family-row">@foreach($items($family) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@endforeach</div>
        </div></section>

        <section class="ec96-promo-categories xd-landing-block" data-landing-block-id="{{ data_get($promos, 'id') }}" data-block-type="ec906_category_promos"><div class="ec96-container">
            @foreach($items($promos) as $index => $item)<a class="ec96-promo-tile ec96-promo-{{ $index + 1 }}" href="{{ data_get($item, 'url', '#') }}"><div><h3>{{ data_get($item, 'title') }}</h3><span>{{ data_get($item, 'button_label', 'Mua ngay') }}</span></div></a>@endforeach
        </div></section>

        <section id="do-dung-nha-bep" class="ec96-section ec96-kitchen xd-landing-block" data-landing-block-id="{{ data_get($kitchen, 'id') }}" data-block-type="ec906_kitchen_products"><div class="ec96-container">
            <div class="ec96-title"><span></span><h2>{{ data_get($kitchen, 'data.title', 'Đồ dùng nhà bếp') }}</h2><span></span></div>
            <p class="ec96-tab">Dụng cụ làm bánh</p>
            @php($kitchenItems = $items($kitchen))
            <div class="ec96-kitchen-grid">
                <div class="ec96-kitchen-products ec96-kitchen-left">@foreach($kitchenItems->take(4) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@endforeach</div>
                <a class="ec96-kitchen-promo" href="#"><img src="{{ data_get($kitchen, 'data.content.promo_image', '/theme-demo/ec906/kitchen-promo.png') }}" alt="Khuyến mãi đồ dùng nhà bếp"><div><small>15 ngày vàng</small><strong>Rộn ràng<br>khuyến mãi</strong><span>Xem ngay</span></div></a>
                <div class="ec96-kitchen-products ec96-kitchen-right">@foreach($kitchenItems->slice(4, 4) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@endforeach</div>
            </div>
        </div></section>

        <section id="tin-tuc" class="ec96-section ec96-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec906_latest_posts"><div class="ec96-container">
            <div class="ec96-title"><span></span><h2>{{ data_get($news, 'data.title', 'Tin tức') }}</h2><span></span></div>
            <div class="ec96-news-grid">@foreach($items($news) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec906/home-care.png')) }}" alt="{{ data_get($item, 'title') }}"></a><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><footer><time><i class="fa-regular fa-calendar"></i> {{ data_get($item, 'date', '05/06/2024') }}</time><a href="{{ data_get($item, 'url', '#') }}">Xem chi tiết</a></footer></div></article>@endforeach</div>
        </div></section>

        <section class="ec96-brands xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec906_brand_strip"><div class="ec96-container">@foreach($items($brands) as $item)<span>{{ data_get($item, 'title') }}</span>@endforeach</div></section>
        <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec906_newsletter"></section>
    </div>
</main>
@endsection
