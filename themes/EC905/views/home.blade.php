@extends('theme-ec905::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC905')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $benefits = $get('ec905_benefits');
    $paint = $get('ec905_paint_products');
    $tiles = $get('ec905_tile_products');
    $projects = $get('ec905_projects');
    $news = $get('ec905_news');
    $newsletter = $get('ec905_newsletter');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $categories = [
        ['fa-toilet','Bồn cầu, thiết bị vệ sinh'], ['fa-sink','Chậu rửa mặt, vòi chậu'],
        ['fa-shower','Sen tắm, bồn tắm'], ['fa-pump-soap','Phụ kiện nhà tắm'],
        ['fa-kitchen-set','Thiết bị nhà bếp'], ['fa-paint-roller','Sơn nội & ngoại thất'],
        ['fa-border-all','Gạch ốp lát cao cấp'], ['fa-lightbulb','Đèn và thiết bị điện'],
        ['fa-faucet-drip','Thiết bị lọc nước'], ['fa-house','Chăm sóc nhà cửa'],
    ];
@endphp
<main class="ec95-main">
    <section class="ec95-hero-wrap xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec95-container ec95-hero-grid">
            <aside class="ec95-side-categories">
                <h2><i class="fa-solid fa-bars"></i> Danh mục sản phẩm</h2>
                @foreach($categories as [$icon,$label])<a href="#son-noi-ngoai"><i class="fa-solid {{ $icon }}"></i><span>{{ $label }}</span><i class="fa-solid fa-angle-right"></i></a>@endforeach
            </aside>
            <div class="ec95-hero" data-ec95-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5200) }}">
                @forelse($slides as $index => $slide)
                    <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec95-slide>
                        <img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec905/hero-bathroom.webp')) }}" alt="{{ data_get($slide, 'title') }}">
                        <div class="ec95-hero-copy">
                            <small>{{ data_get($slide, 'badge', 'GIẢI PHÁP NHÀ ĐẸP 2026') }}</small>
                            <h1>{{ data_get($slide, 'title', 'Kiến tạo phòng tắm hiện đại') }}</h1>
                            <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                            <a class="ec95-button" href="{{ data_get($slide, 'link_url', '#son-noi-ngoai') }}">{{ data_get($slide, 'button_label', 'Khám phá ngay') }}</a>
                        </div>
                    </article>
                @empty
                    <article class="is-active" data-ec95-slide><img src="/theme-demo/ec905/hero-bathroom.webp" alt="Phòng tắm hiện đại"></article>
                @endforelse
                <div class="ec95-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec95-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
            </div>
            <aside class="ec95-promo-stack">
                <a href="#op-lat" class="is-orange"><img src="/theme-demo/ec905/product-07.webp" alt=""><span><b>COMBO PHÒNG TẮM</b>Đồng bộ tiện nghi</span></a>
                <a href="#du-an" class="is-blue"><img src="/theme-demo/ec905/product-10.webp" alt=""><span><b>THIẾT BỊ NHÀ BẾP</b>Bền đẹp, dễ vệ sinh</span></a>
                <a href="#son-noi-ngoai" class="is-gold"><img src="/theme-demo/ec905/product-02.webp" alt=""><span><b>SƠN NHÀ ĐÓN NẮNG</b>Màu bền theo năm tháng</span></a>
            </aside>
        </div>
    </section>

    <section class="ec95-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec905_benefits"><div class="ec95-container">
        @foreach($items($benefits) as $index => $item)<article><i class="fa-solid {{ data_get($item, 'icon', ['fa-truck-fast','fa-award','fa-headset','fa-user-check'][$index % 4]) }}"></i><div><b>{{ data_get($item, 'title') }}</b><span>{{ data_get($item, 'summary') }}</span></div></article>@endforeach
    </div></section>

    <section id="son-noi-ngoai" class="ec95-section xd-landing-block" data-landing-block-id="{{ data_get($paint, 'id') }}" data-block-type="ec905_paint_products"><div class="ec95-container">
        <header class="ec95-section-head"><h2><i class="fa-solid fa-paint-roller"></i> {{ data_get($paint, 'data.title', 'Sơn nội & ngoại thất') }}</h2><nav><a href="#">Sơn nội thất</a><a href="#">Sơn ngoại thất</a><a href="#">Sơn chống thấm</a><a href="#">Sơn lót</a></nav></header>
        <div class="ec95-paint-layout">
            <a class="ec95-feature-promo" href="#"><img src="/theme-demo/ec905/project-06.webp" alt="Tư vấn màu sơn"><div><small>GỢI Ý PHỐI MÀU</small><b>Không gian đẹp bắt đầu từ sắc màu phù hợp</b></div></a>
            <div class="ec95-paint-products">@foreach($items($paint) as $item)@include('theme-ec905::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </div></section>

    <section id="op-lat" class="ec95-section xd-landing-block" data-landing-block-id="{{ data_get($tiles, 'id') }}" data-block-type="ec905_tile_products"><div class="ec95-container">
        <header class="ec95-section-head"><h2><i class="fa-solid fa-border-all"></i> {{ data_get($tiles, 'data.title', 'Ốp lát cao cấp') }}</h2><nav><a href="#">Gạch ốp tường</a><a href="#">Gạch chân tường</a><a href="#">Gạch trang trí</a></nav></header>
        <div class="ec95-tile-grid">@foreach($items($tiles) as $item)@include('theme-ec905::partials.product-card', ['item' => $item, 'tile' => true])@endforeach</div>
    </div></section>

    <section id="du-an" class="ec95-section xd-landing-block" data-landing-block-id="{{ data_get($projects, 'id') }}" data-block-type="ec905_projects"><div class="ec95-container">
        <header class="ec95-section-head"><h2>{{ data_get($projects, 'data.title', 'Dự án thi công nổi bật') }}</h2></header>
        @php $projectItems = $items($projects); @endphp
        <div class="ec95-project-grid">@foreach($projectItems as $index => $item)<article class="{{ $index === 0 ? 'is-large' : '' }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec905/project-01.webp')) }}" alt="{{ data_get($item, 'title') }}"><span><i class="fa-solid {{ $index === 2 ? 'fa-circle-play' : 'fa-image' }}"></i></span><h3>{{ data_get($item, 'title') }}</h3></article>@endforeach</div>
    </div></section>

    <section id="tin-tuc" class="ec95-section xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec905_news"><div class="ec95-container">
        @php $newsItems = $items($news); @endphp
        <div class="ec95-news-columns"><section><header class="ec95-section-head"><h2>{{ data_get($news, 'data.title', 'Tin tức khuyến mại') }}</h2></header><div class="ec95-news-featured">@foreach($newsItems->take(2) as $item)<article><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p></article>@endforeach</div></section>
        <section><header class="ec95-section-head"><h2>Tin tức nổi bật</h2></header><div class="ec95-news-list">@foreach($newsItems->slice(2,3) as $item)<article><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"><h3>{{ data_get($item, 'title') }}</h3></article>@endforeach</div></section></div>
    </div></section>

    <section id="newsletter" class="ec95-newsletter xd-landing-block" data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec905_newsletter"><div class="ec95-container"><i class="fa-regular fa-envelope-open"></i><div><h2>{{ data_get($newsletter, 'data.title', 'Đăng ký nhận bản tin') }}</h2><p>{{ data_get($newsletter, 'data.description', 'Tin mới nhất về sản phẩm và mã giảm giá.') }}</p><form><input type="email" placeholder="Nhập email của bạn"><button>Đăng ký <i class="fa-solid fa-paper-plane"></i></button></form></div></div></section>
</main>
@endsection
