@extends('theme-nt503::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'WolfBed')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()
        ->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $themeItems = fn (array $block) => $items($block)
        ->filter(fn ($item) => ! filled(data_get($item, 'url')) || str_contains((string) data_get($item, 'url'), 'nt503-'))
        ->values();

    $hero = $get('hero_slider');
    $categories = $get('nt503_categories');
    $mattresses = $get('nt503_mattresses');
    $promos = $get('nt503_promo_banners');
    $flash = $get('nt503_flash_sale');
    $kids = $get('nt503_kids_collection');
    $season = $get('nt503_season_promo');
    $advice = $get('nt503_advice');
    $footerBlock = $get('nt503_footer');

    $fallbackProducts = collect([
        ['title' => 'Nệm foam Goodnight Eva gấp 3 nâng đỡ', 'price' => 3099000, 'original_price' => 4599000, 'image' => '/theme-demo/nt503/mattress.png', 'url' => '#'],
        ['title' => 'Nệm foam tổng hợp Erica Smart Tech', 'price' => 4299000, 'original_price' => 4999000, 'image' => '/theme-demo/nt503/mattress.png', 'url' => '#'],
        ['title' => 'Nệm lò xo Amando Faro 5 vùng', 'price' => 14490000, 'original_price' => 23990000, 'image' => '/theme-demo/nt503/mattress.png', 'url' => '#'],
        ['title' => 'Nệm lò xo đa tầng Sleep Wave Hybrid', 'price' => 4400000, 'original_price' => 6900000, 'image' => '/theme-demo/nt503/mattress.png', 'url' => '#'],
        ['title' => 'Gối cao su Liên Á Oval đàn hồi cao', 'price' => 810000, 'image' => '/theme-demo/nt503/kids-pillow.png', 'url' => '#'],
        ['title' => 'Gối cao su Kim Cương lượn sóng', 'price' => 610000, 'original_price' => 640000, 'image' => '/theme-demo/nt503/kids-pillow.png', 'url' => '#'],
        ['title' => 'Gối bông trẻ em Deepsleep Khủng Long', 'price' => 300000, 'original_price' => 400000, 'image' => '/theme-demo/nt503/kids-pillow.png', 'url' => '#'],
        ['title' => 'Gối ôm cao su Gummi Body thiên nhiên', 'price' => 1200000, 'original_price' => 1400000, 'image' => '/theme-demo/nt503/kids-pillow.png', 'url' => '#'],
        ['title' => 'Bộ chăn ga Cotton Thảo Mộc', 'price' => 1290000, 'original_price' => 1690000, 'image' => '/theme-demo/nt503/bedding.png', 'url' => '#'],
    ]);
    $products = fn (array $block) => $themeItems($block)->concat($fallbackProducts)->unique(fn ($item) => data_get($item, 'title'))->values();
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values()
        ->whenEmpty(fn ($list) => $list->push([
            'title' => 'WolfBed CUTIE',
            'summary' => 'Êm dịu và nâng niu giấc mơ của con',
            'button_label' => 'Xem thêm sản phẩm',
            'image' => '/theme-demo/nt503/hero-wolfbed.png',
            'url' => '#san-pham',
        ]));
@endphp

<main class="n503-main">
    <section class="n503-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-n503-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6500) }}">
        @foreach($slides as $index => $slide)
            <article class="n503-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-n503-slide>
                <img src="{{ $image($slide, '/theme-demo/nt503/hero-wolfbed.png') }}" alt="{{ data_get($slide, 'title', 'WolfBed') }}">
                <div class="n503-hero-copy">
                    <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title', 'WolfBed CUTIE')) }}</h1>
                    <a href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#san-pham')) }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Xem thêm sản phẩm')) }} <span>›</span></a>
                </div>
            </article>
        @endforeach
        <div class="n503-hero-tabs"><span>TOPPER DOWNFEEL</span><span>SERENE BLOOMING</span><span>WOLF BED CUTIE</span><span>LAST CHANCE DEAL</span></div>
    </section>

    <section id="san-pham" class="n503-section n503-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="nt503_categories">
        <div class="n503-container">
            <h2>{{ data_get($categories, 'data.title', 'Trọn bộ sản phẩm cho giấc ngủ của bạn') }}</h2>
            <div class="n503-tabs"><button class="is-active">Chăn ga</button><button>Gối</button><button>Nệm</button><button>Phụ kiện</button></div>
            <div class="n503-category-grid">
                @foreach($items($categories)->take(10) as $item)
                    <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/nt503/bedding.png') }}" alt="{{ data_get($item, 'title') }}"><b>{{ data_get($item, 'title') }}</b></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="n503-section xd-landing-block" data-landing-block-id="{{ data_get($mattresses, 'id') }}" data-block-type="nt503_mattresses">
        <div class="n503-container">
            <h2>{{ data_get($mattresses, 'data.title', 'Nệm êm giá mềm') }}</h2>
            <div class="n503-product-grid n503-four">@foreach($products($mattresses)->filter(fn ($item) => str_starts_with((string) data_get($item, 'title'), 'Nệm'))->take(4) as $item) @include('theme-nt503::partials.product-card', ['item' => $item]) @endforeach</div>
        </div>
    </section>

    <section class="n503-section n503-promos xd-landing-block" data-landing-block-id="{{ data_get($promos, 'id') }}" data-block-type="nt503_promo_banners">
        <div class="n503-container">
            <article><img src="{{ data_get($promos, 'settings.image_left', '/theme-demo/nt503/bedding.png') }}" alt=""><div><small>GIẤC NGỦ TỪ THIÊN NHIÊN</small><b>Chăn ga dịu êm</b></div></article>
            <article><img src="{{ data_get($promos, 'settings.image_right', '/theme-demo/nt503/kids-pillow.png') }}" alt=""><div><small>CAO SU THIÊN NHIÊN</small><b>Nâng đỡ trọn giấc</b></div></article>
        </div>
    </section>

    <section id="flash-sale" class="n503-section n503-flash xd-landing-block" data-landing-block-id="{{ data_get($flash, 'id') }}" data-block-type="nt503_flash_sale">
        <div class="n503-container">
            <header><strong>FLASH SALE</strong><h2>{{ data_get($flash, 'data.title', 'Giá tốt, Ưu đãi khủng') }}</h2></header>
            <div class="n503-product-grid n503-five">@foreach($products($flash)->take(5) as $item) @include('theme-nt503::partials.product-card', ['item' => $item, 'sale' => true]) @endforeach</div>
        </div>
    </section>

    <section class="n503-section n503-kids xd-landing-block" data-landing-block-id="{{ data_get($kids, 'id') }}" data-block-type="nt503_kids_collection">
        <div class="n503-container">
            <header><div><small>BỘ SƯU TẬP MỚI</small><h2>{{ data_get($kids, 'data.title', 'BST Drap Trẻ em') }}</h2></div><a href="#">{{ data_get($kids, 'data.button_label', 'Khám phá BST') }} <span>›</span></a></header>
            <img class="n503-kids-cover" src="{{ data_get($kids, 'settings.cover_image', '/theme-demo/nt503/hero-wolfbed.png') }}" alt="Bộ sưu tập trẻ em">
            <div class="n503-product-grid n503-four">@foreach($products($kids)->filter(fn ($item) => str_starts_with((string) data_get($item, 'title'), 'Gối'))->take(4) as $item) @include('theme-nt503::partials.product-card', ['item' => $item]) @endforeach</div>
        </div>
    </section>

    <section class="n503-section n503-season xd-landing-block" data-landing-block-id="{{ data_get($season, 'id') }}" data-block-type="nt503_season_promo">
        <div class="n503-container">
            <img src="{{ data_get($season, 'settings.background_image', '/theme-demo/nt503/bedding.png') }}" alt="">
            <div><h2>{{ data_get($season, 'data.title', 'Ngủ ngon mỗi ngày với giá ưu đãi') }}</h2><p>{{ data_get($season, 'data.description', 'Giảm 30% toàn bộ bộ sưu tập mới nhất') }}</p><a href="#">{{ data_get($season, 'data.button_label', 'Mua ngay') }}</a></div>
        </div>
    </section>

    <section class="n503-section n503-advice xd-landing-block" data-landing-block-id="{{ data_get($advice, 'id') }}" data-block-type="nt503_advice">
        <div class="n503-container">
            <h2>{{ data_get($advice, 'data.title', 'Góc tư vấn') }}</h2>
            <div class="n503-advice-grid">@foreach($themeItems($advice)->take(4) as $item)@php $adviceImage = ['/theme-demo/nt503/mattress.png','/theme-demo/nt503/hero-wolfbed.png','/theme-demo/nt503/kids-pillow.png','/theme-demo/nt503/bedding.png'][$loop->index % 4]; @endphp<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $adviceImage }}" alt="{{ data_get($item, 'title') }}"><h3>{{ data_get($item, 'title') }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary')), 110) }}</p><time>{{ data_get($item, 'published_at') }}</time></a></article>@endforeach</div>
        </div>
    </section>

    <section class="n503-footer-intro xd-landing-block" data-landing-block-id="{{ data_get($footerBlock, 'id') }}" data-block-type="nt503_footer">
        <div class="n503-container"><h2>{{ data_get($footerBlock, 'data.title', 'Wolf Bed') }}</h2><p>{{ data_get($footerBlock, 'data.description', 'Mua nệm, chăn ga gối và phụ kiện chính hãng. Tư vấn cá nhân hoá, nằm thử 120 đêm, đổi trả dễ và giao tận nơi.') }}</p><h3>Hotline hỗ trợ</h3><div><b>Tư vấn mua hàng<br>1900 6750 (Nhánh 1)</b><b>Hỗ trợ kỹ thuật<br>1900 6750 (Nhánh 2)</b><b>Góp ý, khiếu nại<br>1900 6750</b></div></div>
    </section>
</main>
@endsection
