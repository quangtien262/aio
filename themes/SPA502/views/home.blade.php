@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = collect(data_get($landingEditorOptions ?? [], 'locales', []))->all();
    $block = fn (string $type, int $offset = 0) => $blocks->where('block_type', $type)->values()->get($offset, []);
    $items = function (array $block): array {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty()
            ? $dynamic->all()
            : collect(data_get($block, 'data.content.items', []))->filter()->values()->all();
    };
    $fallbackImage = 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1400&q=85';
    $hero = $block('hero_slider');
    $categories = $block('featured_categories');
    $promos = $block('collection_gallery');
    $products = $block('featured_products', 0);
    $deals = $block('content_mosaic');
    $intro = $block('content_showcase');
    $posts = $block('latest_posts');
    $partners = $block('partner_logos');
    $slides = collect(data_get($hero, 'data.content.slides', []))->whenEmpty(fn () => collect($hero['dynamic_items'] ?? []))->values();
    if ($slides->isEmpty()) {
        $slides = collect([[
            'kicker' => 'Bùng nổ khai trương cơ sở mới',
            'title' => 'Viện thẩm mỹ Cosmetics',
            'summary' => 'Giảm giá 50% tất cả các dịch vụ',
            'button_label' => 'Xem thêm',
            'link_url' => '#dich-vu',
            'image' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=2200&q=88',
        ]]);
    }
    $productRenderer = function (array $item, bool $compact = false): string {
        $title = e((string) data_get($item, 'title', data_get($item, 'name', 'Sản phẩm')));
        $image = e((string) data_get($item, 'image', 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=800&q=85'));
        $url = e((string) data_get($item, 'url', '#'));
        $price = data_get($item, 'price');
        $oldPrice = data_get($item, 'old_price');
        $sale = data_get($item, 'sale_label');
        $sold = data_get($item, 'sold_label');
        $tags = collect(data_get($item, 'tags', ['Mới']))->take(2);
        $stars = str_repeat('★', (int) data_get($item, 'rating', 0)).str_repeat('☆', max(0, 5 - (int) data_get($item, 'rating', 0)));
        $html = '<a class="spa502-product-card" href="'.$url.'"><span class="spa502-product-image">';
        if (filled($sale)) $html .= '<b class="spa502-sale">'.e((string) $sale).'</b>';
        $html .= '<i class="spa502-heart fa-regular fa-heart"></i><img src="'.$image.'" alt="'.$title.'"><span class="spa502-tags">';
        foreach ($tags as $index => $tag) {
            $html .= '<em class="spa502-tag '.($index === 1 ? 'is-hot' : '').'">'.e((string) $tag).'</em>';
        }
        $html .= '</span></span><h3>'.$title.'</h3>';
        if (filled($price)) {
            $html .= '<span class="spa502-price">'.e((string) $price);
            if (filled($oldPrice)) $html .= ' <del>'.e((string) $oldPrice).'</del>';
            $html .= '</span>';
        }
        if ($compact && filled($sold)) $html .= '<span class="spa502-progress"><i></i></span><p>Đã bán <strong>'.e((string) $sold).'</strong></p>';
        $html .= '<span class="spa502-stars">'.$stars.'</span></a>';
        return $html;
    };
@endphp

@extends('theme-spa502::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'SPA502 HALU Cosmetics')))

@section('content')
<main class="spa502-main">
    <section class="spa502-hero">
        @foreach($slides as $index => $slide)
            <article class="spa502-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-spa502-hero-slide>
                <span class="spa502-hero__fallback"></span>
                <img src="{{ data_get($slide, 'image', $fallbackImage) }}" alt="{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}">
                <div class="spa502-container spa502-hero__copy">
                    <div class="spa502-hero__text">
                        <small>{{ data_get($slide, 'kicker', data_get($slide, 'subtitle', data_get($hero, 'data.subtitle'))) }}</small>
                        <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                        <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                        <a href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#dich-vu')) }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Xem thêm')) }}</a>
                    </div>
                </div>
            </article>
        @endforeach
        <button class="spa502-hero__nav is-prev" type="button" data-spa502-hero-prev aria-label="Slide trước"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="spa502-hero__nav is-next" type="button" data-spa502-hero-next aria-label="Slide sau"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="spa502-hero__dots">
            @foreach($slides as $index => $slide)<span class="{{ $index === 0 ? 'is-active' : '' }}" data-spa502-hero-dot></span>@endforeach
        </div>
    </section>

    <section id="dich-vu" class="spa502-service-cats">
        <div class="spa502-container">
            <div class="spa502-title">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($categories, 'data.title', 'Dịch Vụ Đa Dạng') }}</h2>
                <p>{{ data_get($categories, 'data.subtitle', 'Toàn bộ dịch vụ sản phẩm') }}</p>
            </div>
            <div class="spa502-category-rail">
                @foreach($items($categories) as $index => $item)
                    <a class="spa502-category {{ $index === 2 ? 'is-featured' : '' }}" href="{{ data_get($item, 'url', '#') }}">
                        <span class="spa502-category__image" data-badge="{{ data_get($item, 'badge', $index === 2 ? '50% Off' : '') }}">
                            <img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'title') }}">
                        </span>
                        <h3>{{ data_get($item, 'title') }}</h3>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="uu-dai" class="spa502-promos">
        <div class="spa502-container">
            <div class="spa502-title">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($promos, 'data.title', 'Gói Ưu Đãi Đặc Biệt') }}</h2>
                <p>{{ data_get($promos, 'data.subtitle', 'Hãy đến và trải nghiệm ngay hôm nay!') }}</p>
            </div>
            <div class="spa502-promo-grid">
                @foreach($items($promos) as $item)
                    <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'title') }}"></a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="spa502-section spa502-products">
        <div class="spa502-container">
            <div class="spa502-title">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($products, 'data.title', 'Sản Phẩm') }}</h2>
                <p>{{ data_get($products, 'data.subtitle', 'Đảm bảo chất lượng số 1 Việt Nam') }}</p>
            </div>
            <div class="spa502-product-grid">
                @foreach($items($products) as $item)
                    {!! $productRenderer($item) !!}
                @endforeach
            </div>
        </div>
    </section>

    <section id="deal" class="spa502-deals" style="--deal-bg:url('{{ data_get($deals, 'media.background_image', 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1800&q=80') }}')">
        <div class="spa502-container spa502-deals__grid">
            <div class="spa502-deal-copy">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($deals, 'data.title', 'Deal Cực Hấp Dẫn') }}</h2>
                <p>{{ data_get($deals, 'data.subtitle', 'Chương trình giảm giá:') }}</p>
                <span>{{ data_get($deals, 'data.description', 'Expired') }}</span>
            </div>
            <div class="spa502-deal-rail">
                @foreach($items($deals) as $item)
                    {!! $productRenderer($item, true) !!}
                @endforeach
            </div>
        </div>
    </section>

    <section id="gioi-thieu" class="spa502-section spa502-intro">
        <div class="spa502-container">
            <div class="spa502-title">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($intro, 'data.title', 'Kem Dưỡng Da Dịu Nhẹ') }}</h2>
                <p>{{ data_get($intro, 'data.subtitle', 'Biến đổi tình trạng da') }}</p>
            </div>
            <div class="spa502-intro__grid">
                <div class="spa502-intro__copy">
                    <h3>{{ data_get($intro, 'data.content.heading', 'Kem dưỡng da - Có tác dụng rất nhanh') }}</h3>
                    <p>{{ data_get($intro, 'data.description', 'Sản phẩm dịu nhẹ giúp cấp ẩm, phục hồi và chăm sóc làn da sau liệu trình thẩm mỹ.') }}</p>
                </div>
                <div class="spa502-intro__image">
                    <img src="{{ data_get($intro, 'data.content.image', 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85') }}" alt="{{ data_get($intro, 'data.title') }}">
                </div>
                <div class="spa502-benefits">
                    @foreach(collect(data_get($intro, 'data.content.items', []))->take(5) as $item)
                        <span><i class="{{ data_get($item, 'icon', 'fa-solid fa-leaf') }}"></i>{{ data_get($item, 'title', data_get($item, 'summary')) }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="tin-tuc" class="spa502-posts">
        <div class="spa502-container">
            <div class="spa502-title">
                <span class="spa502-lotus"><i></i><b>✦</b><i></i></span>
                <h2>{{ data_get($posts, 'data.title', 'Tin Tức') }}</h2>
                <p>{{ data_get($posts, 'data.subtitle', 'Những bài viết mới nhất') }}</p>
            </div>
            <div class="spa502-post-grid">
                @foreach($items($posts) as $item)
                    <a class="spa502-post-card" href="{{ data_get($item, 'url', '#') }}">
                        <span class="spa502-post-card__media">
                            <img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'title') }}">
                            <time><i class="fa-regular fa-calendar-days"></i>{{ data_get($item, 'date', '30/10/2023') }}</time>
                        </span>
                        <span class="spa502-post-card__body">
                            <h3>{{ \Illuminate\Support\Str::limit(data_get($item, 'title'), 70) }}</h3>
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags(data_get($item, 'summary', '')), 125) }}</p>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="doi-tac" class="spa502-partners">
        <div class="spa502-container spa502-partners__rail">
            @foreach($items($partners) as $item)
                <a href="{{ data_get($item, 'url', '#') }}">
                    @if(filled(data_get($item, 'logo', data_get($item, 'image'))))
                        <img src="{{ data_get($item, 'logo', data_get($item, 'image')) }}" alt="{{ data_get($item, 'name', data_get($item, 'title')) }}">
                    @else
                        <strong>{{ data_get($item, 'name', data_get($item, 'title')) }}</strong>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
</main>
@endsection
