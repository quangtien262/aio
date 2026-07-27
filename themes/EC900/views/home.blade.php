@extends('theme-ec900::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC900')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $hero = $get('hero_slider');
    $categories = $get('ec900_featured_categories');
    $best = $get('ec900_best_sellers');
    $needs = $get('ec900_need_mosaic');
    $campaign = $get('ec900_campaign_mosaic');
    $exclusive = $get('ec900_exclusive_products');
    $brand = $get('ec900_brand_banner');
    $advice = $get('ec900_advice_posts');
    $categoryItems = $items($categories);
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $needItems = $items($needs);
    $campaignItems = $items($campaign);
    $adviceItems = $items($advice);
@endphp
<main class="ec9-main">
    <section class="ec9-hero-section xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec9-container ec9-hero-grid">
            <aside class="ec9-category-rail" data-ec9-category-rail>
                @foreach($categoryItems->take(9) as $item)
                    <a href="{{ data_get($item, 'url', '#') }}">
                        <img src="{{ $image($item, '/theme-demo/ec900/air-fryer.webp') }}" alt="">
                        <span>{{ data_get($item, 'title') }}</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                @endforeach
            </aside>
            <div class="ec9-hero-slider" data-ec9-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5600) }}">
                @forelse($slides as $index => $slide)
                    <article class="ec9-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-ec9-slide>
                        <img src="{{ $image($slide, '/theme-demo/ec900/hero-appliances.webp') }}" alt="{{ data_get($slide, 'title') }}">
                        <div class="ec9-hero-copy">
                            <small>{{ data_get($slide, 'badge', data_get($slide, 'eyebrow', 'ƯU ĐÃI ĐỘC QUYỀN')) }}</small>
                            <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                            <p>{{ data_get($slide, 'summary', data_get($slide, 'subtitle', data_get($hero, 'data.description'))) }}</p>
                            <a href="{{ data_get($slide, 'link_url', '#san-pham-ban-chay') }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Mua ngay')) }} <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                    </article>
                @empty
                    <article class="ec9-hero-slide is-active" data-ec9-slide><img src="/theme-demo/ec900/hero-appliances.webp" alt="Thiết bị gia dụng thông minh"></article>
                @endforelse
                <button class="ec9-slide-prev" type="button" data-ec9-prev aria-label="Slide trước"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="ec9-slide-next" type="button" data-ec9-next aria-label="Slide sau"><i class="fa-solid fa-chevron-right"></i></button>
                <div class="ec9-dots">@foreach($slides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" data-ec9-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
            </div>
        </div>
    </section>

    <section id="{{ data_get($categories, 'anchor_id', 'danh-muc-noi-bat') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec900_featured_categories">
        <div class="ec9-container ec9-panel">
            <h2>{{ data_get($categories, 'data.title', 'Danh mục nổi bật') }}</h2>
            <div class="ec9-categories">@foreach($categoryItems as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec900/air-fryer.webp') }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div>
        </div>
    </section>

    <section id="{{ data_get($best, 'anchor_id', 'san-pham-ban-chay') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($best, 'id') }}" data-block-type="ec900_best_sellers">
        <div class="ec9-container ec9-sale-panel">
            <h2>{{ data_get($best, 'data.title', 'Top sản phẩm bán chạy') }}</h2>
            <div class="ec9-product-grid">@foreach($items($best) as $item)@include('theme-ec900::partials.product-card', ['item' => $item])@endforeach</div>
            <a class="ec9-more" href="{{ route('site.catalog.search') }}">{{ data_get($best, 'data.button_label', 'Xem thêm sản phẩm') }} <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section id="{{ data_get($needs, 'anchor_id', 'lua-chon-phu-hop') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($needs, 'id') }}" data-block-type="ec900_need_mosaic">
        <div class="ec9-container">
            <h2>{{ data_get($needs, 'data.title', 'Lựa chọn phù hợp với mọi nhu cầu') }}</h2>
            <div class="ec9-needs">
                <a class="ec9-need-lead" href="{{ data_get($needs, 'settings.feature_url', '#san-pham-dac-quyen') }}"><img src="{{ data_get($needs, 'settings.feature_image', '/theme-demo/ec900/home-promo.webp') }}" alt=""><span>{{ data_get($needs, 'data.subtitle', 'Ưu đãi thiết bị cho tổ ấm') }}</span></a>
                @foreach($needItems->chunk(4)->take(3) as $group)
                    <article class="ec9-need-group"><h3>{{ data_get($group->first(), 'group', ['Nấu cơm sang, mịn', 'Sống khỏe mỗi ngày', 'Nhà nhỏ sắm đồ gọn'][$loop->index] ?? 'Thiết bị thông minh') }}</h3><div>@foreach($group as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec900/robot-vacuum.webp') }}" alt=""><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="{{ data_get($campaign, 'anchor_id', 'khuyen-mai') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($campaign, 'id') }}" data-block-type="ec900_campaign_mosaic">
        <div class="ec9-container ec9-panel">
            <h2>{{ data_get($campaign, 'data.title', 'Nhà gọn gàng, việc nhà nhẹ tênh') }}</h2>
            <div class="ec9-campaigns">
                @foreach($campaignItems as $item)<a href="{{ data_get($item, 'url', '#san-pham-dac-quyen') }}"><img src="{{ $image($item, '/theme-demo/ec900/home-promo.webp') }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach
            </div>
        </div>
    </section>

    <section id="{{ data_get($exclusive, 'anchor_id', 'san-pham-dac-quyen') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($exclusive, 'id') }}" data-block-type="ec900_exclusive_products">
        <div class="ec9-container ec9-panel">
            <h2>{{ data_get($exclusive, 'data.title', 'Sản phẩm đặc quyền') }}</h2>
            <div class="ec9-tabs">@foreach($categoryItems->take(6) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec900/air-purifier.webp') }}" alt=""><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div>
            <div class="ec9-product-grid ec9-product-grid-large">@foreach($items($exclusive) as $item)@include('theme-ec900::partials.product-card', ['item' => $item])@endforeach</div>
        </div>
    </section>

    <section id="{{ data_get($brand, 'anchor_id', 'thuong-hieu') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($brand, 'id') }}" data-block-type="ec900_brand_banner">
        <div class="ec9-container">@foreach($items($brand)->take(1) as $item)<a class="ec9-brand-banner" href="{{ data_get($item, 'url', '#san-pham-dac-quyen') }}"><img src="{{ $image($item, '/theme-demo/ec900/tv-lifestyle.webp') }}" alt="{{ data_get($item, 'title') }}"><span><small>{{ data_get($item, 'eyebrow', 'CÔNG NGHỆ CHO TỔ ẤM') }}</small><b>{{ data_get($item, 'title') }}</b></span></a>@endforeach</div>
    </section>

    <section id="{{ data_get($advice, 'anchor_id', 'tu-van-san-pham') }}" class="ec9-section xd-landing-block" data-landing-block-id="{{ data_get($advice, 'id') }}" data-block-type="ec900_advice_posts">
        <div class="ec9-container ec9-panel">
            <h2>{{ data_get($advice, 'data.title', 'Tư vấn sản phẩm') }}</h2>
            <div class="ec9-advice">
                @if($feature = $adviceItems->first())
                    <article class="ec9-advice-feature"><a href="{{ data_get($feature, 'url', '#') }}"><img src="{{ $image($feature, '/theme-demo/ec900/air-purifier.webp') }}" alt=""></a><h3><a href="{{ data_get($feature, 'url', '#') }}">{{ data_get($feature, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($feature, 'summary')), 190) }}</p><time><i class="fa-regular fa-calendar"></i> {{ data_get($feature, 'published_at', now()->format('d/m/Y')) }}</time></article>
                @endif
                <div class="ec9-advice-list">@foreach($adviceItems->skip(1)->take(3) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/ec900/washing-machine.webp') }}" alt=""></a><div><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($item, 'summary')), 100) }}</p><time><i class="fa-regular fa-calendar"></i> {{ data_get($item, 'published_at', now()->format('d/m/Y')) }}</time></div></article>@endforeach</div>
            </div>
        </div>
    </section>
</main>
@endsection
