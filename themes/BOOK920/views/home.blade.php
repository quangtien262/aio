@extends('theme-book920::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'Bookle')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider');
    $benefits = $get('book920_benefits');
    $featured = $get('book920_featured');
    $sale = $get('book920_sale');
    $promo = $get('book920_promo');
    $hot = $get('book920_hot');
    $testimonials = $get('book920_testimonials');
    $posts = $get('latest_posts');
    $footer = $get('book920_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }
@endphp
<main class="book20-main">
    <section class="book20-hero xd-landing-block" data-book20-slider data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        @forelse($slides as $index => $slide)
            <article class="{{ $index === 0 ? 'is-active' : '' }}" data-book20-slide>
                <img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/book920/hero-bookstore.png')) }}" alt="{{ data_get($slide, 'title', 'Bookle') }}">
                @if(filled(data_get($slide, 'title')))<div><span>{{ data_get($slide, 'badge') }}</span><h1>{{ data_get($slide, 'title') }}</h1><p>{{ data_get($slide, 'summary') }}</p></div>@endif
            </article>
        @empty
            <article class="is-active" data-book20-slide><img src="/theme-demo/book920/hero-bookstore.png" alt="Không gian nhà sách Bookle"></article>
        @endforelse
        <div class="book20-dots"><button class="is-active"></button><button></button></div>
    </section>

    <section id="dich-vu" class="book20-section book20-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits, 'id') }}" data-block-type="book920_benefits" data-book20-reveal>
        <div class="book20-container book20-benefit-grid">
            @foreach($items($benefits) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-award') }}"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach
        </div>
    </section>

    @foreach([[$featured, 'Sách nổi bật', 'book920_featured'], [$sale, 'Sách khuyến mãi', 'book920_sale']] as [$block, $fallbackTitle, $type])
    <section class="book20-section book20-products xd-landing-block" data-landing-block-id="{{ data_get($block, 'id') }}" data-block-type="{{ $type }}" data-book20-reveal>
        <div class="book20-container">
            <div class="book20-section-head"><h2>{{ data_get($block, 'data.title', $fallbackTitle) }}</h2><a href="{{ route('site.catalog.search') }}">Khám phá thêm <i class="fa-solid fa-arrow-right"></i></a></div>
            <div class="book20-product-grid">@foreach($items($block) as $item)@include('theme-book920::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>
    @endforeach

    <section class="book20-section book20-promo xd-landing-block" data-landing-block-id="{{ data_get($promo, 'id') }}" data-block-type="book920_promo" data-book20-reveal>
        <div class="book20-container"><div><small>ƯU ĐÃI DÀNH CHO BẠN</small><h2>{{ data_get($promo, 'data.title', 'Giảm giá 25% cho tất cả các loại sách bán chạy') }}</h2><a href="#sach-hot">Mua ngay <i class="fa-solid fa-arrow-right"></i></a></div><div class="book20-promo-books">@foreach(range(1,3) as $index)<img src="/theme-demo/book920/book-{{ $index }}.webp" alt="Sách bán chạy">@endforeach</div></div>
    </section>

    <section id="sach-hot" class="book20-section book20-hot xd-landing-block" data-landing-block-id="{{ data_get($hot, 'id') }}" data-block-type="book920_hot" data-book20-reveal>
        <div class="book20-container"><div class="book20-section-head"><h2>{{ data_get($hot, 'data.title', 'Sản phẩm HOT') }}</h2><a href="{{ route('site.catalog.search') }}">Khám phá thêm <i class="fa-solid fa-arrow-right"></i></a></div><div class="book20-hot-grid">@foreach($items($hot) as $item)@include('theme-book920::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section id="gioi-thieu" class="book20-section book20-testimonials xd-landing-block" data-landing-block-id="{{ data_get($testimonials, 'id') }}" data-block-type="book920_testimonials" data-book20-reveal>
        <div class="book20-container"><div class="book20-section-head center"><h2>{{ data_get($testimonials, 'data.title', 'Khách hàng của chúng tôi nói gì') }}</h2></div><div class="book20-testimonial-grid">@foreach($items($testimonials) as $item)<article><p>“{{ data_get($item, 'summary') }}”</p><div><span>{{ mb_substr(data_get($item, 'title', 'B'), 0, 1) }}</span><strong>{{ data_get($item, 'title') }}<small>{{ data_get($item, 'role') }}</small></strong></div></article>@endforeach</div></div>
    </section>

    <section id="tin-tuc" class="book20-section book20-posts xd-landing-block" data-landing-block-id="{{ data_get($posts, 'id') }}" data-block-type="latest_posts" data-book20-reveal>
        <div class="book20-container"><div class="book20-section-head center"><div><h2>{{ data_get($posts, 'data.title', 'Tin tức mới nhất') }}</h2><p>{{ data_get($posts, 'data.summary', 'Cảm hứng đọc sách và những câu chuyện mới từ Bookle.') }}</p></div></div><div class="book20-post-grid">@foreach($items($posts) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a><small><i class="fa-regular fa-calendar"></i> {{ data_get($item, 'published_at', '26/06/2026') }} &nbsp; <i class="fa-regular fa-eye"></i> {{ data_get($item, 'views', 88) }}</small><h3>{{ data_get($item, 'title') }}</h3><a href="{{ data_get($item, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right"></i></a></article>@endforeach</div></div>
    </section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="book920_footer"></section>
</main>
@endsection
