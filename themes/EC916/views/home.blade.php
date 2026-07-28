@extends('theme-ec916::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Bách Hóa Xanh Plus')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider'); $categories = $get('ec916_categories'); $featured = $get('ec916_featured_deals');
    $promo = $get('ec916_promo_pair'); $beauty = $get('ec916_beauty_deals'); $campaigns = $get('ec916_campaign_mosaic');
    $brands = $get('ec916_brands'); $newsletter = $get('ec916_newsletter'); $footer = $get('ec916_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec16-main">
    <section class="ec16-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div data-ec16-slider>
            @forelse($slides as $index => $slide)
                <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec16-slide><img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec916/hero-mega-sale.webp')) }}" alt="{{ data_get($slide, 'title') }}"><div class="ec16-hero-copy"><span>{{ data_get($slide, 'badge', 'ĐẠI TIỆC MUA SẮM') }}</span><h1>{{ data_get($slide, 'title', 'Hàng ngàn ưu đãi – Giá tốt mỗi ngày') }}</h1><p>{{ data_get($slide, 'summary', 'Hàng chính hãng, giao nhanh tận nơi và đổi trả dễ dàng.') }}</p><a href="#noi-bat">Mua ngay <i class="fa-solid fa-arrow-right"></i></a></div></article>
            @empty
                <article class="is-active" data-ec16-slide><img src="/theme-demo/ec916/hero-mega-sale.webp" alt="Đại tiệc mua sắm"><div class="ec16-hero-copy"><span>ĐẠI TIỆC MUA SẮM</span><h1>Hàng ngàn ưu đãi – Giá tốt mỗi ngày</h1></div></article>
            @endforelse
            <button class="prev" data-ec16-prev><i class="fa-solid fa-chevron-left"></i></button><button class="next" data-ec16-next><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </section>

    <section id="danh-muc" class="ec16-section ec16-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec916_categories" data-ec16-reveal>
        <div class="ec16-container"><div class="ec16-heading"><span>KHÁM PHÁ NHANH</span><h2>{{ data_get($categories, 'data.title', 'Danh mục nổi bật') }}</h2></div><div class="ec16-category-grid">@foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#noi-bat') }}" data-ec16-stagger><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></a>@endforeach</div></div>
    </section>

    <section id="noi-bat" class="ec16-section ec16-products-section xd-landing-block" data-landing-block-id="{{ data_get($featured, 'id') }}" data-block-type="ec916_featured_deals" data-ec16-reveal>
        <div class="ec16-container"><div class="ec16-section-bar"><h2>{{ data_get($featured, 'data.title', 'Sản phẩm nổi bật') }}</h2><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a></div><div class="ec16-product-grid">@foreach($items($featured) as $item)@include('theme-ec916::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section class="ec16-section ec16-promo-pair xd-landing-block" data-landing-block-id="{{ data_get($promo, 'id') }}" data-block-type="ec916_promo_pair" data-ec16-reveal>
        <div class="ec16-container">@foreach($items($promo) as $item)<a href="{{ data_get($item, 'url', '#lam-dep') }}" data-ec16-stagger><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><small>{{ data_get($item, 'badge') }}</small><b>{{ data_get($item, 'title') }}</b><em>{{ data_get($item, 'summary') }}</em><strong>Mua ngay</strong></span></a>@endforeach</div>
    </section>

    <section id="lam-dep" class="ec16-section ec16-products-section ec16-beauty xd-landing-block" data-landing-block-id="{{ data_get($beauty, 'id') }}" data-block-type="ec916_beauty_deals" data-ec16-reveal>
        <div class="ec16-container"><div class="ec16-section-bar pink"><h2><i class="fa-solid fa-spa"></i> {{ data_get($beauty, 'data.title', 'Sức khỏe & Làm đẹp') }}</h2><span>Chăm sóc da · Chăm sóc tóc · Chăm sóc cơ thể</span></div><div class="ec16-product-grid">@foreach($items($beauty) as $item)@include('theme-ec916::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section id="chien-dich" class="ec16-section ec16-campaigns xd-landing-block" data-landing-block-id="{{ data_get($campaigns, 'id') }}" data-block-type="ec916_campaign_mosaic" data-ec16-reveal>
        <div class="ec16-container"><div class="ec16-heading"><span>ƯU ĐÃI THEO CHỦ ĐỀ</span><h2>{{ data_get($campaigns, 'data.title', 'Chiến dịch mua sắm trong tuần') }}</h2></div><div class="ec16-campaign-grid">@foreach($items($campaigns) as $index => $item)<a class="item-{{ $index + 1 }}" href="{{ data_get($item, 'url', '#noi-bat') }}" data-ec16-stagger><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><small>{{ data_get($item, 'badge') }}</small><b>{{ data_get($item, 'title') }}</b><em>{{ data_get($item, 'summary') }}</em></span></a>@endforeach</div></div>
    </section>

    <section id="thuong-hieu" class="ec16-section ec16-brands xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec916_brands" data-ec16-reveal>
        <div class="ec16-container"><div class="ec16-heading"><span>ĐỒNG HÀNH CÙNG</span><h2>{{ data_get($brands, 'data.title', 'Thương hiệu được yêu thích') }}</h2></div><div class="ec16-brand-row">@foreach($items($brands) as $item)<span data-ec16-stagger>{{ data_get($item, 'title') }}</span>@endforeach</div></div>
    </section>

    <section class="ec16-newsletter xd-landing-block" data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec916_newsletter" data-ec16-reveal>
        <div class="ec16-container" data-ec16-motion="scale"><div><h2>{{ data_get($newsletter, 'data.title', 'Đăng ký nhận thông tin ưu đãi và khuyến mãi') }}</h2><p>{{ data_get($newsletter, 'data.summary', 'Thông tin của bạn được bảo mật và có thể hủy đăng ký bất cứ lúc nào.') }}</p></div><form><input type="email" placeholder="Nhập địa chỉ Email..."><button aria-label="Đăng ký"><i class="fa-solid fa-paper-plane"></i></button></form></div>
    </section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec916_footer"></section>
</main>
@endsection
