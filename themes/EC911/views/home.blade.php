@extends('theme-ec911::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'DIGITECH')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $benefits = $get('ec911_benefits');
    $categories = $get('ec911_category_rail');
    $flash = $get('ec911_flash_sale');
    $cameras = $get('ec911_camera_products');
    $campaign = $get('ec911_campaign_banner');
    $brands = $get('ec911_brand_cards');
    $news = $get('ec911_news');
    $newsletter = $get('ec911_newsletter');
    $footer = $get('ec911_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec11-main">
<section class="ec11-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
    <div class="ec11-container ec11-hero-grid">
        <div class="ec11-hero-main" data-ec11-slider>
            @forelse($slides as $index => $slide)
                <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec11-slide style="background-image:linear-gradient(90deg,rgba(236,70,95,.12),rgba(59,72,211,.03)),url('{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec911/hero-vlog.png')) }}')">
                    <div><span>Máy quay Vlog</span><h1>{{ data_get($slide, 'title', 'Khơi nguồn sáng tạo') }}</h1><p>{{ data_get($slide, 'summary', 'Thiết bị ghi hình nhỏ gọn dành cho nhà sáng tạo nội dung') }}</p><a href="{{ data_get($slide, 'link_url', '#flash-sale') }}">Khám phá ngay</a></div>
                </article>
            @empty
                <article class="is-active" data-ec11-slide style="background-image:url('/theme-demo/ec911/hero-vlog.png')"><div><span>Máy quay Vlog</span><h1>DIGITECH CREATOR X1</h1><p>Ghi trọn mọi khoảnh khắc</p></div></article>
            @endforelse
        </div>
        <aside><a href="#may-anh"><img src="/theme-demo/ec911/camera-pro.png" alt="Máy quay chuyên nghiệp"><b>Máy quay mini chuyên nghiệp</b></a><a href="#tin-tuc"><img src="/theme-demo/ec911/action-camera.webp" alt="Camera hành động"><b>Camera chống rung du lịch</b></a></aside>
    </div>
</section>
<section class="ec11-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec911_benefits"><div class="ec11-container">
    @foreach($items($benefits) as $item)<article><i class="fa-solid {{ data_get($item, 'icon', 'fa-headset') }}"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach
</div></section>
<section class="ec11-category-rail xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec911_category_rail"><div class="ec11-container">
    @foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#') }}"><div><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary', 'Sản phẩm chính hãng') }}</small></div><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec911/camera-pro.png')) }}" alt="{{ data_get($item, 'title') }}"></a>@endforeach
</div></section>
<section id="flash-sale" class="ec11-flash xd-landing-block" data-landing-block-id="{{ data_get($flash, 'id') }}" data-block-type="ec911_flash_sale"><div class="ec11-container">
    <h2>{{ data_get($flash, 'data.title', 'Flash sale') }}</h2><div class="ec11-products">@foreach($items($flash) as $item)@include('theme-ec911::partials.product-card', ['item' => $item, 'flash' => true])@endforeach</div>
</div></section>
<section id="may-anh" class="ec11-product-section xd-landing-block" data-landing-block-id="{{ data_get($cameras, 'id') }}" data-block-type="ec911_camera_products"><div class="ec11-container">
    <header><h2>{{ data_get($cameras, 'data.title', 'MÁY ẢNH') }}</h2><nav><a>Máy ảnh DSLR</a><a>Máy ảnh Mirrorless</a><a>Máy ảnh Compact</a><a>Máy ảnh Film</a><a href="{{ route('site.catalog.search') }}">Xem tất cả</a></nav></header>
    <div class="ec11-products">@foreach($items($cameras) as $item)@include('theme-ec911::partials.product-card', ['item' => $item])@endforeach</div>
</div></section>
<section class="ec11-campaign xd-landing-block" data-landing-block-id="{{ data_get($campaign, 'id') }}" data-block-type="ec911_campaign_banner"><div class="ec11-container"><a href="{{ data_get($campaign, 'settings.link_url', '#may-anh') }}"><img src="{{ data_get($campaign, 'settings.image', '/theme-demo/ec911/campaign-cameras.png') }}" alt="Top camera thịnh hành"><div><b>TOP CAMERA THỊNH HÀNH</b><span>SỐNG TRỌN ĐAM MÊ NHIẾP ẢNH</span></div></a></div></section>
<section class="ec11-brand-cards xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec911_brand_cards"><div class="ec11-container">
    @foreach($items($brands) as $item)<a href="{{ data_get($item, 'url', '#may-anh') }}"><img src="{{ data_get($item, 'image', '/theme-demo/ec911/camera-pro.png') }}" alt="{{ data_get($item, 'title') }}"><strong>{{ data_get($item, 'title') }}</strong></a>@endforeach
</div></section>
<section id="tin-tuc" class="ec11-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec911_news"><div class="ec11-container"><h2>{{ data_get($news, 'data.title', 'TIN TỨC') }}</h2><div>
    @foreach($items($news) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec911/news-camera.webp')) }}" alt="{{ data_get($item, 'title') }}"></a><h3>{{ data_get($item, 'title') }}</h3><p class="meta"><i class="fa-regular fa-user"></i> Team DIGI <i class="fa-regular fa-clock"></i> {{ data_get($item, 'date', '10/05/2026') }}</p><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right-long"></i></a></article>@endforeach
</div></div></section>
<section id="newsletter" class="ec11-newsletter xd-landing-block" data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec911_newsletter"><div class="ec11-container"><div><h2>ĐĂNG KÝ ĐỂ NHẬN TIN TỨC KHUYẾN MÃI MỚI NHẤT</h2><p>Bạn hãy để lại email để không bỏ lỡ sản phẩm và các chương trình khuyến mãi.</p></div><form action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Nhập email của bạn" required><button>Gửi</button></form></div></section>
<section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec911_footer"></section>
</main>
@endsection
