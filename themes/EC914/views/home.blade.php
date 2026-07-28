@extends('theme-ec914::layout')

@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Mộc Nhiên Craft')))

@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $categories = $get('ec914_category_rail');
    $sale = $get('ec914_craft_sale');
    $featured = $get('ec914_featured_products');
    $collections = $get('ec914_collection_gallery');
    $baskets = $get('ec914_basket_showcase');
    $lamps = $get('ec914_lamp_showcase');
    $story = $get('ec914_artisan_story');
    $testimonials = $get('ec914_testimonials');
    $partners = $get('ec914_partners');
    $news = $get('ec914_latest_posts');
    $footer = $get('ec914_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp

<main class="ec14-main">
    <section class="ec14-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec14-hero-slider" data-ec14-slider>
            @forelse($slides as $index => $slide)
                <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec14-slide>
                    <img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec914/hero-craft.webp')) }}" alt="{{ data_get($slide, 'title') }}">
                    <div class="ec14-hero-overlay"></div>
                    <div class="ec14-hero-copy"><span>{{ data_get($slide, 'badge', 'CHẠM VÀO VẺ ĐẸP MỘC') }}</span><h1>{{ data_get($slide, 'title', 'Mỗi món đồ là một câu chuyện mộc mạc') }}</h1><p>{{ data_get($slide, 'summary', 'Từng sợi mây đan, từng đường tre uốn đều mang theo hơi thở thiên nhiên và sự khéo léo của người thợ Việt.') }}</p><a href="{{ data_get($slide, 'link_url', '#noi-bat') }}">Khám phá ngay <i class="fa-solid fa-arrow-right"></i></a></div>
                </article>
            @empty
                <article class="is-active" data-ec14-slide><img src="/theme-demo/ec914/hero-craft.webp" alt="Đồ thủ công mây tre"><div class="ec14-hero-overlay"></div><div class="ec14-hero-copy"><span>CHẠM VÀO VẺ ĐẸP MỘC</span><h1>Mỗi món đồ là một câu chuyện mộc mạc</h1><p>Thủ công truyền thống gặp thiết kế hiện đại.</p><a href="#noi-bat">Khám phá ngay</a></div></article>
            @endforelse
            <div class="ec14-slider-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec14-dot="{{ $index }}"></button>@endforeach</div>
        </div>
    </section>

    <section class="ec14-category-rail xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec914_category_rail">
        <div class="ec14-container"><div class="ec14-category-grid">@foreach($items($categories) as $item)<a href="{{ data_get($item, 'url', '#noi-bat') }}"><span><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></span><b>{{ data_get($item, 'title', data_get($item, 'name')) }}</b><small>{{ data_get($item, 'summary', 'Sản phẩm thủ công') }}</small></a>@endforeach</div></div>
    </section>

    <section id="sale" class="ec14-section ec14-sale xd-landing-block" data-landing-block-id="{{ data_get($sale, 'id') }}" data-block-type="ec914_craft_sale">
        <div class="ec14-container ec14-sale-panel">
            <div class="ec14-section-head"><span>CÙNG MỘC NHIÊN KHỞI ĐỘNG</span><h2>{{ data_get($sale, 'data.title', 'Year End Craft Sale – Xả kho cuối mùa') }}</h2><p>{{ data_get($sale, 'data.summary', 'Ưu đãi dành cho những món đồ thủ công được yêu thích nhất.') }}</p></div>
            <div class="ec14-sale-bar"><b>Ưu đãi đang diễn ra</b><div class="ec14-countdown" data-ec14-countdown data-end="{{ data_get($sale, 'settings.end_at', now()->addDays(30)->toIso8601String()) }}"><span><b data-days>00</b><small>Ngày</small></span><span><b data-hours>00</b><small>Giờ</small></span><span><b data-minutes>00</b><small>Phút</small></span><span><b data-seconds>00</b><small>Giây</small></span></div></div>
            <div class="ec14-product-grid ec14-sale-grid">@foreach($items($sale) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@endforeach</div>
            <a class="ec14-more" href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section id="noi-bat" class="ec14-section xd-landing-block" data-landing-block-id="{{ data_get($featured, 'id') }}" data-block-type="ec914_featured_products">
        <div class="ec14-container"><div class="ec14-section-head"><span>THỦ CÔNG MỘC NHIÊN</span><h2>{{ data_get($featured, 'data.title', 'Các sản phẩm nổi bật') }}</h2><p>{{ data_get($featured, 'data.summary', 'Sự kết hợp hài hòa giữa thủ công truyền thống và thiết kế hiện đại.') }}</p></div><div class="ec14-filter-pills"><b>Túi xách & phụ kiện</b><span>Đèn mây tre</span><span>Giỏ & khay</span><span>Decor tự nhiên</span></div><div class="ec14-product-grid">@foreach($items($featured) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section id="bo-suu-tap" class="ec14-section ec14-collections xd-landing-block" data-landing-block-id="{{ data_get($collections, 'id') }}" data-block-type="ec914_collection_gallery">
        <div class="ec14-container"><div class="ec14-section-head"><span>THỦ CÔNG MỘC NHIÊN</span><h2>{{ data_get($collections, 'data.title', 'Bộ sưu tập mới nhất') }}</h2><p>{{ data_get($collections, 'data.summary', 'Mộc mạc nhưng tinh tế, lưu giữ hơi thở của thiên nhiên.') }}</p></div><div class="ec14-collection-grid">@foreach($items($collections) as $index => $item)<a class="item-{{ $index + 1 }}" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach</div></div>
    </section>

    <section class="ec14-section ec14-showcase xd-landing-block" data-landing-block-id="{{ data_get($baskets, 'id') }}" data-block-type="ec914_basket_showcase">
        <div class="ec14-container ec14-showcase-layout"><div class="ec14-showcase-image"><img src="/theme-demo/ec914/collection-wall.webp" alt="Giỏ và khay đan tay"></div><div class="ec14-showcase-content"><div class="ec14-section-head align-left"><span>THỦ CÔNG MỘC NHIÊN</span><h2>{{ data_get($baskets, 'data.title', 'Giỏ & Khay Đan Tay') }}</h2><p>{{ data_get($baskets, 'data.summary', 'Từng sợi mây đan thủ công, lưu giữ vẻ tự nhiên cho không gian sống.') }}</p></div><div class="ec14-product-grid compact">@foreach($items($baskets) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@endforeach</div></div></div>
    </section>

    <section class="ec14-section ec14-showcase is-reverse xd-landing-block" data-landing-block-id="{{ data_get($lamps, 'id') }}" data-block-type="ec914_lamp_showcase">
        <div class="ec14-container ec14-showcase-layout"><div class="ec14-showcase-image"><img src="/theme-demo/ec914/collection-lamps.webp" alt="Đèn mây tre trang trí"></div><div class="ec14-showcase-content"><div class="ec14-section-head align-left"><span>ÁNH SÁNG TỪ THIÊN NHIÊN</span><h2>{{ data_get($lamps, 'data.title', 'Đèn Mây Tre Trang Trí') }}</h2><p>{{ data_get($lamps, 'data.summary', 'Ánh sáng len qua sợi mây, mang hơi thở thiên nhiên và sự ấm áp.') }}</p></div><div class="ec14-product-grid compact">@foreach($items($lamps) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@endforeach</div></div></div>
    </section>

    <section id="cau-chuyen" class="ec14-section ec14-story xd-landing-block" data-landing-block-id="{{ data_get($story, 'id') }}" data-block-type="ec914_artisan_story">
        <div class="ec14-container ec14-story-layout"><img src="{{ data_get($story, 'media.image', '/theme-demo/ec914/artisan-story.webp') }}" alt="Nghệ nhân đan mây"><div><span>GIỮ GÌN NGHỀ THỦ CÔNG</span><h2>{{ data_get($story, 'data.title', 'Câu chuyện từ những đôi tay') }}</h2><p>{{ data_get($story, 'data.summary', 'Chúng tôi tôn vinh chất liệu tự nhiên và kỹ nghệ của người thợ Việt. Mỗi thiết kế là sự gặp gỡ giữa ký ức truyền thống và thẩm mỹ đương đại.') }}</p><a href="{{ route('site.contact') }}">Đọc câu chuyện <i class="fa-solid fa-arrow-right"></i></a></div></div>
    </section>

    <section class="ec14-section ec14-testimonials xd-landing-block" data-landing-block-id="{{ data_get($testimonials, 'id') }}" data-block-type="ec914_testimonials">
        <div class="ec14-container ec14-testimonial-layout"><img src="/theme-demo/ec914/testimonial-craft.webp" alt="Sản phẩm mây tre"><div><span>KHÁCH HÀNG NÓI VỀ CHÚNG TÔI</span><h2>{{ data_get($testimonials, 'data.title', 'Mang một góc làng nghề về nhà') }}</h2>@foreach($items($testimonials)->take(1) as $item)<blockquote><i class="fa-solid fa-quote-left"></i><p>{{ data_get($item, 'summary') }}</p><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'role') }}</small></blockquote>@endforeach</div></div>
    </section>

    <section class="ec14-partners xd-landing-block" data-landing-block-id="{{ data_get($partners, 'id') }}" data-block-type="ec914_partners"><div class="ec14-container">@foreach($items($partners) as $item)<span>{{ data_get($item, 'title') }}</span>@endforeach</div></section>

    <section class="ec14-section ec14-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec914_latest_posts">
        <div class="ec14-container"><div class="ec14-section-head align-left"><span>CHUYỆN NHÀ MỘC</span><h2>{{ data_get($news, 'data.title', 'Tin tức & cảm hứng mới nhất') }}</h2></div><div class="ec14-news-grid">@foreach($items($news) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"></a><div><small>Cảm hứng sống</small><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p><a href="{{ data_get($item, 'url', '#') }}">Đọc thêm <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach</div></div>
    </section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec914_footer"></section>
</main>
@endsection
