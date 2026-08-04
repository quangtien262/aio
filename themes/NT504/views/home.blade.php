@extends('theme-nt504::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Wolf Paint')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()
        ->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $fallbackProducts = collect([
        ['title' => 'Sơn nội thất Premium Silk', 'price' => 605000, 'original_price' => 900000, 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
        ['title' => 'Sơn ngoại thất Weather Shield', 'price' => 1078000, 'original_price' => 1290000, 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
        ['title' => 'Sơn chống thấm WaterGuard', 'price' => 4950000, 'original_price' => 5350000, 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
        ['title' => 'Sơn lót kháng kiềm cao cấp', 'price' => 720000, 'original_price' => 820000, 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
        ['title' => 'Sơn hiệu ứng bê tông', 'price' => 1280000, 'original_price' => 1450000, 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
    ]);
    $products = fn (array $block) => $items($block)->concat($fallbackProducts)->filter(fn ($item) => filled(data_get($item, 'title')))->unique(fn ($item) => data_get($item, 'title'))->values();

    $hero = $get('hero_slider');
    $spaces = $get('nt504_spaces');
    $productCategories = $get('nt504_product_categories');
    $premium = $get('nt504_premium_promo');
    $categoryRail = $get('nt504_category_rail');
    $sale = $get('nt504_sale_products');
    $servicePromos = $get('nt504_service_promos');
    $news = $get('nt504_latest_news');
    $footerBlock = $get('nt504_footer');

    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }
    if ($slides->isEmpty()) {
        $slides = collect([['title' => 'Sơn nhà đẹp bắt đầu từ một màu sắc đúng', 'summary' => 'Bảng màu thời thượng, bền đẹp vượt trội và thân thiện với môi trường.', 'image' => '/theme-demo/nt504/hero.png', 'button_label' => 'Khám phá bộ sưu tập', 'link_url' => '#san-pham']]);
    }
    $spaceDefaults = [
        ['title' => 'Phòng khách', 'summary' => '126+ bảng màu', 'icon' => 'fa-solid fa-couch'],
        ['title' => 'Phòng ngủ', 'summary' => '96+ bảng màu', 'icon' => 'fa-solid fa-bed'],
        ['title' => 'Nhà bếp', 'summary' => '72+ bảng màu', 'icon' => 'fa-solid fa-kitchen-set'],
        ['title' => 'Mặt tiền', 'summary' => '150+ bảng màu', 'icon' => 'fa-solid fa-building'],
        ['title' => 'Văn phòng', 'summary' => '60+ bảng màu', 'icon' => 'fa-solid fa-city'],
    ];
    $categoryDefaults = [
        ['title' => 'Sơn nội thất', 'summary' => 'Bền đẹp, an toàn cho không gian sống'],
        ['title' => 'Sơn ngoại thất', 'summary' => 'Bền màu, vượt thời tiết'],
        ['title' => 'Sơn chống thấm', 'summary' => 'Bảo vệ tối ưu, ngăn thấm vượt trội'],
        ['title' => 'Sơn lót & Chất phủ', 'summary' => 'Tăng độ bám dính, bề mặt láng mịn'],
    ];
    $railDefaults = ['Sơn nội thất', 'Sơn ngoại thất', 'Sơn chống thấm', 'Sơn lót & Chất phủ', 'Sơn đặc biệt', 'Bột trét tường', 'Dụng cụ thi công', 'Sơn lót chống kiềm'];
    $fallbackNews = collect([
        ['title' => 'Phong cách Zen là gì? 20 mẫu phối màu hợp kiến trúc', 'summary' => 'Khám phá vẻ đẹp cân bằng và kết nối sâu sắc giữa con người với thiên nhiên.', 'published_at' => '30 Tháng 06, 2026', 'image' => '/theme-demo/nt504/hero.png', 'url' => '#'],
        ['title' => 'Phong cách Wabi Sabi và gợi ý màu sắc phù hợp', 'summary' => 'Vẻ đẹp tĩnh lặng, mộc mạc và sự trân trọng những điều tự nhiên.', 'published_at' => '30 Tháng 06, 2026', 'image' => '/theme-demo/nt504/spaces.png', 'url' => '#'],
        ['title' => 'Sơn chống cháy là gì? Phân loại và cơ chế hoạt động', 'summary' => 'Tìm hiểu cấu tạo và cách lựa chọn giải pháp bảo vệ phù hợp.', 'published_at' => '30 Tháng 06, 2026', 'image' => '/theme-demo/nt504/promo.png', 'url' => '#'],
        ['title' => 'Quy trình sơn chống thấm ngoài trời chuẩn kỹ thuật', 'summary' => 'Các bước thi công giúp bề mặt bền chắc và hạn chế ẩm mốc.', 'published_at' => '30 Tháng 06, 2026', 'image' => '/theme-demo/nt504/paint-categories.png', 'url' => '#'],
    ]);
    $newsItems = $items($news)->concat($fallbackNews)->filter(fn ($item) => filled(data_get($item, 'title')))->unique(fn ($item) => data_get($item, 'title'))->values();
    $leadNews = $newsItems->first();
@endphp

<main class="n504-main">
    <section class="n504-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-n504-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6500) }}">
        @foreach($slides as $index => $slide)
            <article class="n504-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-n504-slide style="--n504-hero-image:url('{{ $image($slide, '/theme-demo/nt504/hero.png') }}')">
                <div class="n504-container n504-hero-inner">
                    <div class="n504-hero-copy">
                        <span><i class="fa-solid fa-seedling"></i> {{ data_get($slide, 'badge', 'BST MÀU SẮC MỚI 2026') }}</span>
                        <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                        <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                        <div><a class="n504-btn" href="{{ data_get($slide, 'link_url', '#san-pham') }}"><i class="fa-solid fa-cart-shopping"></i> {{ data_get($slide, 'button_label', 'Khám phá bộ sưu tập') }}</a><a class="n504-btn n504-btn-ghost" href="#video"><i class="fa-regular fa-circle-play"></i> Xem video</a></div>
                    </div>
                </div>
            </article>
        @endforeach
        <div class="n504-benefit-bar">
            <div><i class="fa-solid fa-shield-halved"></i><span><small>Bảo hành</small><b>24 tháng</b></span></div>
            <div><i class="fa-solid fa-rotate"></i><span><small>1 đổi 1</small><b>Trong 30 ngày</b></span></div>
            <div><i class="fa-solid fa-truck"></i><span><small>Miễn phí</small><b>Giao hàng</b></span></div>
            <div><i class="fa-solid fa-leaf"></i><span><small>Thân thiện</small><b>Môi trường</b></span></div>
        </div>
    </section>

    <section class="n504-section n504-spaces xd-landing-block" data-landing-block-id="{{ data_get($spaces, 'id') }}" data-block-type="nt504_spaces">
        <div class="n504-container n504-spaces-layout">
            <header class="n504-intro"><small>{{ data_get($spaces, 'data.subtitle', 'CHỌN MÀU SƠN THEO') }}</small><h2>{{ data_get($spaces, 'data.title', 'Không gian sống') }}</h2><p>{{ data_get($spaces, 'data.description', 'Khám phá các bảng màu được chuyên gia phối sẵn cho từng không gian trong nhà.') }}</p><a class="n504-outline" href="{{ route('site.catalog.search') }}">Xem tất cả không gian</a></header>
            <div class="n504-space-grid">
                @foreach(collect($items($spaces))->concat($spaceDefaults)->take(5) as $index => $item)
                    <a href="{{ data_get($item, 'url', '#san-pham') }}"><span class="n504-sprite n504-space-sprite" style="--i:{{ $index }}"></span><i class="{{ data_get($item, 'icon', data_get($spaceDefaults, $index.'.icon', 'fa-regular fa-image')) }}"></i><b>{{ data_get($item, 'title', data_get($spaceDefaults, $index.'.title')) }}</b><small>{{ data_get($item, 'summary', data_get($spaceDefaults, $index.'.summary')) }}</small></a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="n504-section n504-product-categories xd-landing-block" data-landing-block-id="{{ data_get($productCategories, 'id') }}" data-block-type="nt504_product_categories">
        <div class="n504-container"><header class="n504-section-head"><div><small>{{ data_get($productCategories, 'data.subtitle', 'DANH MỤC SẢN PHẨM') }}</small><h2>{{ data_get($productCategories, 'data.title', 'Sản phẩm chất lượng cho mọi công trình') }}</h2></div><a href="{{ route('site.catalog.search') }}">Xem tất cả sản phẩm <i class="fa-solid fa-arrow-right"></i></a></header>
            <div class="n504-category-grid">
                @foreach(collect($items($productCategories))->concat($categoryDefaults)->take(4) as $index => $item)
                    <a href="{{ data_get($item, 'url', '#') }}"><span class="n504-sprite n504-category-sprite" style="--i:{{ $index }}"></span><div><h3>{{ data_get($item, 'title', data_get($categoryDefaults, $index.'.title')) }}</h3><p>{{ data_get($item, 'summary', data_get($categoryDefaults, $index.'.summary')) }}</p></div><i class="fa-solid fa-arrow-right"></i></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="n504-premium xd-landing-block" data-landing-block-id="{{ data_get($premium, 'id') }}" data-block-type="nt504_premium_promo" style="--premium-image:url('{{ data_get($premium, 'settings.background_image', '/theme-demo/nt504/promo.png') }}')">
        <div class="n504-container"><div class="n504-premium-copy"><small>{{ data_get($premium, 'data.subtitle', 'ƯU ĐÃI CÓ HẠN') }}</small><h2>{{ data_get($premium, 'data.title', 'Sắc màu hoàn hảo') }}</h2><h3>Không gian đẳng cấp</h3><p>{{ data_get($premium, 'data.description', 'Khám phá bộ sưu tập sơn cao cấp với công nghệ tiên tiến, mang đến màu sắc bền đẹp và bảo vệ tối ưu cho mọi công trình.') }}</p><div class="n504-promo-benefits"><span><i class="fa-solid fa-percent"></i><b>Giảm đến<br>35%</b></span><span><i class="fa-solid fa-truck"></i><b>Miễn phí<br>vận chuyển</b></span><span><i class="fa-solid fa-gift"></i><b>Quà tặng<br>hấp dẫn</b></span></div><a class="n504-btn" href="{{ route('site.catalog.search') }}">{{ data_get($premium, 'data.button_label', 'Mua ngay') }} <i class="fa-solid fa-arrow-right"></i></a></div></div>
    </section>

    <section class="n504-section n504-category-rail xd-landing-block" data-landing-block-id="{{ data_get($categoryRail, 'id') }}" data-block-type="nt504_category_rail">
        <div class="n504-container"><div class="n504-rail-grid">
            @foreach(collect($items($categoryRail))->concat(collect($railDefaults)->map(fn ($title) => ['title' => $title]))->take(8) as $index => $item)
                <a href="{{ data_get($item, 'url', '#san-pham') }}"><span class="n504-sprite n504-rail-sprite" style="--i:{{ $index % 4 }}"></span><b>{{ data_get($item, 'title') }}</b></a>
            @endforeach
        </div></div>
    </section>

    <section id="khuyen-mai" class="n504-section n504-sale xd-landing-block" data-landing-block-id="{{ data_get($sale, 'id') }}" data-block-type="nt504_sale_products">
        <div class="n504-container"><header class="n504-section-head"><div><small>{{ data_get($sale, 'data.subtitle', 'ƯU ĐÃI NỔI BẬT') }}</small><h2>{{ data_get($sale, 'data.title', 'Sản phẩm khuyến mãi') }}</h2><p>{{ data_get($sale, 'data.description', 'Chọn lựa những sản phẩm chất lượng với mức giá tốt nhất trong tháng này.') }}</p></div><a href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-arrow-right"></i></a></header><div class="n504-product-grid">@foreach($products($sale)->take(5) as $item) @include('theme-nt504::partials.product-card', ['item' => $item, 'position' => $loop->index]) @endforeach</div></div>
    </section>

    <section class="n504-section n504-service-promos xd-landing-block" data-landing-block-id="{{ data_get($servicePromos, 'id') }}" data-block-type="nt504_service_promos">
        <div class="n504-container n504-service-grid">
            @foreach([['Ưu đãi hấp dẫn', 'Giảm đến 20%', 'Mua ngay'], ['Tư vấn màu sắc', 'Miễn phí', 'Đăng ký ngay'], ['Miễn phí giao hàng', 'Toàn quốc', 'Xem chi tiết']] as $index => [$small, $title, $button])
                <article><span class="n504-sprite n504-service-sprite" style="--i:{{ $index }}"></span><div><small>{{ $small }}</small><h3>{{ $title }}</h3><a href="{{ $index === 1 ? route('site.contact') : route('site.catalog.search') }}">{{ $button }} <i class="fa-solid fa-arrow-right"></i></a></div></article>
            @endforeach
        </div>
    </section>

    <section id="tin-tuc" class="n504-section n504-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="nt504_latest_news">
        <div class="n504-container"><header class="n504-section-head"><div><small>{{ data_get($news, 'data.subtitle', 'TIN TỨC & KIẾN THỨC') }}</small><h2>{{ data_get($news, 'data.title', 'Cập nhật tin tức mới nhất') }}</h2><p>{{ data_get($news, 'data.description', 'Khám phá xu hướng, mẹo hay và những kiến thức hữu ích về sơn & không gian sống.') }}</p></div><a href="{{ route('site.blog.index') }}">Xem tất cả tin <i class="fa-solid fa-arrow-right"></i></a></header>
            <div class="n504-news-grid">
                @if($leadNews)<a class="n504-news-lead" href="{{ data_get($leadNews, 'url', '#') }}"><img src="{{ $image($leadNews, '/theme-demo/nt504/hero.png') }}" alt="{{ data_get($leadNews, 'title') }}"><div><time><i class="fa-regular fa-calendar"></i> {{ data_get($leadNews, 'published_at') }}</time><h3>{{ data_get($leadNews, 'title') }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($leadNews, 'summary')), 150) }}</p><b>Đọc thêm <i class="fa-solid fa-arrow-right"></i></b></div></a>@endif
                <div class="n504-news-list">@foreach($newsItems->skip(1)->take(3) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/nt504/spaces.png') }}" alt="{{ data_get($item, 'title') }}"><div><time><i class="fa-regular fa-calendar"></i> {{ data_get($item, 'published_at') }}</time><h3>{{ data_get($item, 'title') }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary')), 95) }}</p><b>Đọc thêm <i class="fa-solid fa-arrow-right"></i></b></div></a>@endforeach</div>
            </div>
        </div>
    </section>

    <section class="n504-footer-marker xd-landing-block" data-landing-block-id="{{ data_get($footerBlock, 'id') }}" data-block-type="nt504_footer" aria-hidden="true"></section>
</main>
@endsection
