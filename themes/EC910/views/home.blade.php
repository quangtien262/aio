@extends('theme-ec910::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', 'Dola Watch - Đồng hồ chính hãng'))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $hero = $get('hero_slider');
    $benefits = $get('ec910_benefits');
    $promotions = $get('ec910_promotions');
    $tabs = $get('ec910_product_tabs');
    $about = $get('ec910_about');
    $men = $get('ec910_mens_watches');
    $orient = $get('ec910_orient_banner');
    $experience = $get('ec910_experience');
    $brands = $get('ec910_brands');
    $footerBlock = $get('ec910_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec10-main">
    <section class="ec10-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec10-slider>
        @forelse($slides as $index => $slide)
            <article class="ec10-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-ec10-slide>
                <img src="{{ $image($slide, '/theme-demo/ec910/hero-watches.webp') }}" alt="{{ data_get($slide, 'title', 'Dola Watch') }}">
                <div class="ec10-hero-copy"><small>{{ data_get($slide, 'badge', 'DOLA WATCH') }}</small><h1>{{ data_get($slide, 'title', 'Tinh hoa thời gian') }}</h1><p>{{ data_get($slide, 'summary', data_get($slide, 'subtitle', 'Đồng hồ chính hãng cho mọi phong cách')) }}</p><a href="{{ data_get($slide, 'link_url', '#khuyen-mai') }}">Khám phá ngay</a></div>
            </article>
        @empty
            <article class="ec10-hero-slide is-active" data-ec10-slide><img src="/theme-demo/ec910/hero-watches.webp" alt="Dola Watch"><div class="ec10-hero-copy"><small>DOLA WATCH</small><h1>Tinh hoa thời gian</h1><p>Đồng hồ chính hãng cho mọi phong cách</p><a href="#khuyen-mai">Khám phá ngay</a></div></article>
        @endforelse
        <button class="ec10-arrow prev" data-ec10-prev aria-label="Trước"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="ec10-arrow next" data-ec10-next aria-label="Sau"><i class="fa-solid fa-chevron-right"></i></button>
    </section>

    <section class="ec10-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec910_benefits">
        <div class="ec10-container">@foreach($items($benefits) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-truck-fast') }}"></i><div><b>{{ data_get($item, 'title') }}</b><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach</div>
    </section>

    <section id="khuyen-mai" class="ec10-section xd-landing-block" data-landing-block-id="{{ data_get($promotions, 'id') }}" data-block-type="ec910_promotions">
        <div class="ec10-container ec10-promo-shell">
            <header><h2><i class="fa-solid fa-bolt"></i>{{ data_get($promotions, 'data.title', 'Khuyến mãi hấp dẫn') }}</h2><span>Ưu đãi dành riêng cho thành viên Dola Watch</span></header>
            <div class="ec10-promo-banners">@foreach(collect(data_get($promotions, 'data.content.banners', []))->take(2) as $banner)<a href="{{ data_get($banner, 'url', '#san-pham-moi') }}"><img src="{{ $image($banner, '/theme-demo/ec910/promo-main.webp') }}" alt="{{ data_get($banner, 'title') }}"><strong>{{ data_get($banner, 'title') }}</strong></a>@endforeach</div>
            <div class="ec10-product-row">@foreach($items($promotions)->take(5) as $item)@include('theme-ec910::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>

    <section id="san-pham-moi" class="ec10-section xd-landing-block" data-landing-block-id="{{ data_get($tabs, 'id') }}" data-block-type="ec910_product_tabs">
        <div class="ec10-container ec10-white-panel">
            <div class="ec10-tabs"><button class="is-active">Sản phẩm mới</button><button>Sản phẩm nổi bật</button><button>Sản phẩm bán chạy</button></div>
            <div class="ec10-product-row">@foreach($items($tabs)->take(5) as $item)@include('theme-ec910::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>

    <section id="gioi-thieu" class="ec10-about xd-landing-block" data-landing-block-id="{{ data_get($about, 'id') }}" data-block-type="ec910_about">
        <div class="ec10-container"><div><span class="ec10-brand-mark"><i class="fa-regular fa-clock"></i>DOLA <b>WATCH</b></span><h2>{{ data_get($about, 'data.title', 'Dola Watch - Đồng hồ chính hãng') }}</h2><p>{{ data_get($about, 'data.description', 'Được thành lập từ năm 2019, Dola Watch chuyên bán đồng hồ chính hãng với chính sách bảo hành minh bạch và dịch vụ tận tâm.') }}</p><p>Đội ngũ am hiểu đồng hồ luôn sẵn sàng giúp bạn chọn được thiết kế phù hợp nhất.</p></div><img src="{{ data_get($about, 'settings.image', '/theme-demo/ec910/lifestyle-source.png') }}" alt="Dola Watch"></div>
    </section>

    <section id="dong-ho-nam" class="ec10-section xd-landing-block" data-landing-block-id="{{ data_get($men, 'id') }}" data-block-type="ec910_mens_watches">
        <div class="ec10-container ec10-white-panel">
            <header class="ec10-ribbon-head"><h2><i class="fa-regular fa-clock"></i>{{ data_get($men, 'data.title', 'Đồng hồ nam') }}</h2><div><button>CHẤT LIỆU DÂY</button><button>THƯƠNG HIỆU HOT</button><button>CÁC DÒNG ĐẶC BIỆT</button></div></header>
            <div class="ec10-men-grid">
                <aside><img src="{{ data_get($men, 'settings.feature_image', '/theme-demo/ec910/watch-men.webp') }}" alt="Đồng hồ nam"><h3>Thương hiệu ưa chuộng nhất</h3><p><span>Citizen</span><span>Orient</span><span>Casio</span><span>Doxa</span></p></aside>
                <div class="ec10-product-grid">@foreach($items($men)->take(8) as $item)@include('theme-ec910::partials.product-card', ['item' => $item])@endforeach</div>
            </div>
        </div>
    </section>

    <section class="ec10-campaign xd-landing-block" data-landing-block-id="{{ data_get($orient, 'id') }}" data-block-type="ec910_orient_banner">
        <div class="ec10-container"><img src="{{ data_get($orient, 'settings.image', '/theme-demo/ec910/promo-main.webp') }}" alt="Orient"><div><small>QUÀ TẶNG ĐỘC QUYỀN</small><h2>{{ data_get($orient, 'data.title', 'Mua đồng hồ Orient') }}</h2><a href="#dong-ho-nam">Xem bộ sưu tập</a></div></div>
    </section>

    <section id="kinh-nghiem" class="ec10-section xd-landing-block" data-landing-block-id="{{ data_get($experience, 'id') }}" data-block-type="ec910_experience">
        <div class="ec10-container ec10-white-panel">
            <header class="ec10-ribbon-head"><h2><i class="fa-regular fa-clock"></i>{{ data_get($experience, 'data.title', 'Kinh nghiệm') }}</h2><div><a href="{{ route('site.blog.index') }}">Tin tức</a><a href="{{ route('site.blog.index') }}">Kiến thức</a></div></header>
            <div class="ec10-news-layout">
                @php($posts = $items($experience))
                @if($lead = $posts->first())<article class="ec10-news-lead"><img src="{{ $image($lead, '/theme-demo/ec910/watch-men.webp') }}" alt="{{ data_get($lead, 'title') }}"><h3>{{ data_get($lead, 'title') }}</h3><time>{{ data_get($lead, 'published_at', now()->format('d/m/Y')) }}</time><p>{{ data_get($lead, 'summary') }}</p></article>@endif
                <div class="ec10-news-list">@foreach($posts->skip(1)->take(4) as $post)<article><img src="{{ $image($post, '/theme-demo/ec910/classic-silver.webp') }}" alt=""><div><h3>{{ data_get($post, 'title') }}</h3><p>{{ data_get($post, 'summary') }}</p></div></article>@endforeach</div>
                <img class="ec10-news-art" src="{{ data_get($experience, 'settings.feature_image', '/theme-demo/ec910/watch-women.webp') }}" alt="Phong cách đồng hồ">
            </div>
        </div>
    </section>

    <section id="thuong-hieu" class="ec10-section xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec910_brands">
        <div class="ec10-container ec10-white-panel"><header class="ec10-ribbon-head"><h2><i class="fa-regular fa-clock"></i>{{ data_get($brands, 'data.title', 'Thương hiệu nổi bật') }}</h2></header><div class="ec10-brands">@foreach($items($brands) as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a>@endforeach</div></div>
    </section>

    <section class="xd-landing-block" hidden data-landing-block-id="{{ data_get($footerBlock, 'id') }}" data-block-type="ec910_footer"></section>
</main>
@endsection
