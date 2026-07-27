@extends('theme-ec909::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Euro Sound')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $about = $get('ec909_about');
    $categories = $get('ec909_category_cards');
    $headphoneShowcase = $get('ec909_headphone_showcase');
    $headphoneProducts = $get('ec909_headphone_products');
    $microphone = $get('ec909_microphone_feature');
    $earphoneProducts = $get('ec909_earphone_products');
    $stereo = $get('ec909_stereo_feature');
    $recommendations = $get('ec909_recommendations');
    $brands = $get('ec909_brand_strip');
    $postsBlock = $get('ec909_latest_posts');
    $benefits = $get('ec909_benefits');
    $footerBlock = $get('ec909_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
@endphp
<main class="ec99-main">
    <section class="ec99-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec99-shell" data-ec99-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6500) }}">
            @forelse($slides as $index => $slide)
                <article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec99-slide style="--hero:url('{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec909/hero-underwater.png')) }}')">
                    <div class="ec99-hero-copy">
                        <h1>{{ data_get($slide, 'title', 'Âm thanh stereo sống động – Kết nối đôi, trải nghiệm gấp bội') }}</h1>
                        <a href="{{ data_get($slide, 'link_url', '#goi-y') }}">{{ data_get($slide, 'button_label', 'Xem ngay') }}</a>
                    </div>
                </article>
            @empty
                <article class="is-active" data-ec99-slide style="--hero:url('/theme-demo/ec909/hero-underwater.png')"><div class="ec99-hero-copy"><h1>Âm thanh stereo sống động<br>Kết nối đôi, trải nghiệm gấp bội</h1><a href="#goi-y">Xem ngay</a></div></article>
            @endforelse
            <div class="ec99-dots"><i></i><i></i><i></i></div>
        </div>
    </section>

    <section class="ec99-about ec99-section xd-landing-block" data-landing-block-id="{{ data_get($about, 'id') }}" data-block-type="ec909_about">
        <div class="ec99-container ec99-about-grid">
            <div class="ec99-about-art"><img src="{{ data_get($about, 'settings.image', '/theme-demo/ec909/about-studio.png') }}" alt="Euro Sound"><img src="{{ data_get($about, 'settings.secondary_image', '/theme-demo/ec909/headphone-black.png') }}" alt="Trải nghiệm âm thanh"></div>
            <div><h2>{{ data_get($about, 'data.title', 'Euro Sound - Âm Thanh Chuẩn, Công Nghệ Chất') }}</h2><p>{{ data_get($about, 'data.summary', 'Euro Sound là cửa hàng chuyên cung cấp các thiết bị âm thanh chính hãng như loa, tai nghe có dây, tai nghe không dây và headphone chất lượng cao.') }}</p><p>{{ data_get($about, 'data.description', 'Với tiêu chí đặt trải nghiệm người dùng lên hàng đầu, Euro Sound luôn chú trọng vào chất lượng sản phẩm, giá thành hợp lý và dịch vụ tận tâm.') }}</p></div>
        </div>
    </section>

    <section class="ec99-categories ec99-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec909_category_cards">
        <div class="ec99-shell ec99-category-grid">
            @foreach($items($categories) as $index => $item)
                <a class="{{ $index === 0 ? 'is-featured' : '' }}" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec909/headphone-black.png')) }}" alt="{{ data_get($item, 'title') }}"><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary', 'Thiết kế tinh tế, âm thanh tuyệt vời.') }}</p><span>→</span></div></a>
            @endforeach
        </div>
    </section>

    <section class="ec99-collection ec99-section xd-landing-block" data-landing-block-id="{{ data_get($headphoneShowcase, 'id') }}" data-block-type="ec909_headphone_showcase" style="--collection:url('{{ data_get($headphoneShowcase, 'settings.background_image', '/theme-demo/ec909/headphone-beige.png') }}')">
        <div class="ec99-shell ec99-collection-grid">
            <div class="ec99-collection-copy"><i class="fa-solid fa-headphones-simple"></i><h2>{{ data_get($headphoneShowcase, 'data.title', 'Âm Thanh Định Hình Phong Cách') }}</h2><p>{{ data_get($headphoneShowcase, 'data.summary', 'Khám phá bộ sưu tập headphones mới với thiết kế tinh giản, chất âm sâu và chi tiết.') }}</p></div>
            <div class="ec99-products-wrap"><div class="ec99-products-row">@foreach($items($headphoneShowcase) as $item) @include('theme-ec909::partials.product-card', ['item' => $item]) @endforeach</div><a class="ec99-wide-link" href="{{ route('site.catalog.search') }}">Xem tất cả sản phẩm <span>→</span></a></div>
        </div>
    </section>

    <section class="ec99-products-feature ec99-section xd-landing-block" data-landing-block-id="{{ data_get($headphoneProducts, 'id') }}" data-block-type="ec909_headphone_products">
        <div class="ec99-shell ec99-products-feature-grid"><a class="ec99-promo-card" href="{{ data_get($headphoneProducts, 'settings.promo_url', '#') }}" style="--promo:url('{{ data_get($headphoneProducts, 'settings.promo_image', '/theme-demo/ec909/about-studio.png') }}')"><div><h2>Headphones</h2><p>Trải nghiệm âm thanh đỉnh cao, thiết kế tinh tế cho mọi phong cách.</p><span>Xem ngay →</span></div></a><div class="ec99-products-row">@foreach($items($headphoneProducts) as $item) @include('theme-ec909::partials.product-card', ['item' => $item]) @endforeach</div></div>
    </section>

    <section class="ec99-split-feature xd-landing-block" data-landing-block-id="{{ data_get($microphone, 'id') }}" data-block-type="ec909_microphone_feature">
        <div><i class="fa-solid fa-microphone-lines"></i><h2>{{ data_get($microphone, 'data.title', 'Micro rõ nét – Thu âm chuẩn xác') }}</h2><p>{{ data_get($microphone, 'data.summary', 'Thiết bị tích hợp hệ thống microphone tối ưu giúp giọng nói luôn nổi bật và rõ ràng trong mọi cuộc gọi.') }}</p></div><img src="{{ data_get($microphone, 'settings.image', '/theme-demo/ec909/earbud-feature.png') }}" alt="Micro rõ nét">
    </section>

    <section class="ec99-products-feature ec99-earphones ec99-section xd-landing-block" data-landing-block-id="{{ data_get($earphoneProducts, 'id') }}" data-block-type="ec909_earphone_products">
        <div class="ec99-shell ec99-products-feature-grid is-reverse"><div class="ec99-products-row">@foreach($items($earphoneProducts) as $item) @include('theme-ec909::partials.product-card', ['item' => $item]) @endforeach</div><a class="ec99-promo-card" href="{{ data_get($earphoneProducts, 'settings.promo_url', '#') }}" style="--promo:url('{{ data_get($earphoneProducts, 'settings.promo_image', '/theme-demo/ec909/earbuds-silver.png') }}')"><div><h2>Earphones</h2><p>Luôn sẵn sàng đồng hành, mang âm nhạc theo bạn mọi nơi.</p><span>Xem ngay →</span></div></a></div>
    </section>

    <section class="ec99-split-feature is-image-first xd-landing-block" data-landing-block-id="{{ data_get($stereo, 'id') }}" data-block-type="ec909_stereo_feature">
        <img src="{{ data_get($stereo, 'settings.image', '/theme-demo/ec909/stereo-feature.png') }}" alt="Âm thanh stereo"><div><i class="fa-solid fa-volume-high"></i><h2>{{ data_get($stereo, 'data.title', 'Âm thanh stereo sống động - Kết nối đôi, trải nghiệm gấp bội') }}</h2><p>{{ data_get($stereo, 'data.summary', 'Nhân đôi trải nghiệm âm thanh ấn tượng bằng cách kết nối hai loa cùng lúc. Ghép nối nhanh chóng chỉ trong vài giây.') }}</p></div>
    </section>

    <section id="goi-y" class="ec99-recommendations ec99-section xd-landing-block" data-landing-block-id="{{ data_get($recommendations, 'id') }}" data-block-type="ec909_recommendations">
        <div class="ec99-shell"><header><h2>{{ data_get($recommendations, 'data.title', 'Gợi ý sản phẩm cho bạn') }}</h2><p>{{ data_get($recommendations, 'data.summary', 'Đây là những sản phẩm chúng tôi tổng hợp lại cho bạn tham khảo') }}</p><nav><button class="is-active">Sản phẩm mới</button><button>Sản phẩm nổi bật</button><button>Sản phẩm bán chạy</button></nav></header><div class="ec99-products-row is-four">@foreach($items($recommendations) as $item) @include('theme-ec909::partials.product-card', ['item' => $item]) @endforeach</div></div>
    </section>

    <section class="ec99-brands ec99-section xd-landing-block" data-landing-block-id="{{ data_get($brands, 'id') }}" data-block-type="ec909_brand_strip">
        <div class="ec99-shell"><h2>{{ data_get($brands, 'data.title', 'Thương hiệu nổi bật') }}</h2><p>{{ data_get($brands, 'data.summary', 'Chúng tôi cung cấp sản phẩm từ các thương hiệu uy tín') }}</p><div>@foreach($items($brands) as $item)<strong>{{ data_get($item, 'title') }}</strong>@endforeach</div></div>
    </section>

    <section class="ec99-news ec99-section xd-landing-block" data-landing-block-id="{{ data_get($postsBlock, 'id') }}" data-block-type="ec909_latest_posts">
        <div class="ec99-shell"><header><h2>{{ data_get($postsBlock, 'data.title', 'Tin tức mới nhất') }}</h2><p>{{ data_get($postsBlock, 'data.summary', 'Thông tin thực tế, dễ hiểu giúp bạn đưa ra quyết định đầu tư hiệu quả') }}</p></header>@php($posts = $items($postsBlock))<div class="ec99-news-grid">
            @if($feature = $posts->first())<article class="ec99-news-feature" style="--news:url('{{ data_get($feature, 'image', data_get($feature, 'image_url', '/theme-demo/ec909/news-audio.png')) }}')"><div><small><i class="fa-regular fa-calendar"></i> {{ data_get($feature, 'date', '25/07/2026') }} &nbsp; <i class="fa-regular fa-comment-dots"></i> 0 bình luận</small><h3>{{ data_get($feature, 'title') }}</h3><p>{{ data_get($feature, 'summary', data_get($feature, 'excerpt')) }}</p><a href="{{ data_get($feature, 'url', '#') }}">Đọc tiếp</a></div></article>@endif
            <div class="ec99-news-list">@foreach($posts->slice(1, 2) as $post)<article><img src="{{ data_get($post, 'image', data_get($post, 'image_url', '/theme-demo/ec909/news-audio.png')) }}" alt="{{ data_get($post, 'title') }}"><div><small><i class="fa-regular fa-calendar"></i> {{ data_get($post, 'date', '25/07/2026') }} &nbsp; <i class="fa-regular fa-comment-dots"></i> 0 bình luận</small><h3>{{ data_get($post, 'title') }}</h3><p>{{ data_get($post, 'summary', data_get($post, 'excerpt')) }}</p><a href="{{ data_get($post, 'url', '#') }}">Đọc tiếp</a></div></article>@endforeach</div>
        </div></div>
    </section>

    <section class="ec99-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="ec909_benefits"><div class="ec99-shell">@foreach($items($benefits) as $item)<article><i class="fa-solid {{ data_get($item, 'icon', 'fa-truck-fast') }}"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach</div></section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footerBlock, 'id') }}" data-block-type="ec909_footer"></section>
</main>
@endsection
