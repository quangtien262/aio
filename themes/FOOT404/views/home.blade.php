@php
    $blocks = collect($landingBlocks ?? [])->filter(fn ($block) => (bool) data_get($block, 'is_visible', true))->values();
    $byAnchor = fn (string $anchor): array => (array) ($blocks->first(fn ($block) => data_get($block, 'anchor_id') === $anchor) ?? []);
    $items = function (array $block) {
        $dynamic = collect(data_get($block, 'dynamic_items', []))->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $byAnchor('top');
    $categoriesBlock = $byAnchor('danh-muc');
    $promoTrio = $byAnchor('gia-tri');
    $dealsBlock = $byAnchor('san-pham-uu-dai');
    $newBlock = $byAnchor('san-pham-moi');
    $couponBlock = $byAnchor('ma-uu-dai');
    $bestBlock = $byAnchor('ban-chay');
    $promoDuo = $byAnchor('thuong-hieu');
    $slides = collect(data_get($hero, 'dynamic_items', []))->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    if ($slides->count() < 3) {
        $slideFallbacks = collect(data_get($promoTrio, 'data.content.items', []))->map(fn ($item) => [
            'kicker' => data_get($item, 'summary'),
            'title' => data_get($item, 'title'),
            'image' => data_get($item, 'image'),
            'link_url' => data_get($item, 'url', '#san-pham-moi'),
        ]);
        $slides = $slides->concat($slideFallbacks)->filter(fn ($item) => filled(data_get($item, 'image')))->take(3)->values();
    }
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn ($block) => filled(data_get($block, 'id')))->keyBy('id')->toArray() : [];
@endphp
@extends('theme-foot404::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'FOOT404')))
@section('content')
<main class="f404-main">
    <section class="f404-hero xd-landing-block" data-f404-reveal data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="{{ data_get($hero, 'block_type', 'hero_slider') }}">
        @include('theme-foot404::partials.edit-button', ['block' => $hero])
        <div class="f404-container f404-hero__grid">
            @php $lead = $slides->first(); @endphp
            <a class="f404-hero__lead" href="{{ data_get($lead, 'link_url', '#san-pham-uu-dai') }}">
                <img src="{{ data_get($lead, 'image', '/theme-demo/ec903/food-banquet.webp') }}" alt="{{ data_get($lead, 'alt', data_get($lead, 'title')) }}">
                <span><small>{{ data_get($lead, 'kicker', data_get($hero, 'data.subtitle')) }}</small><h1>{{ data_get($lead, 'title', data_get($hero, 'data.title')) }}</h1><p>{{ data_get($lead, 'summary', data_get($hero, 'data.description')) }}</p><b>{{ data_get($lead, 'button_label', data_get($hero, 'data.button_label', 'Khám phá ngay')) }} <i class="fa-solid fa-arrow-right"></i></b></span>
            </a>
            <div class="f404-hero__side">
                @foreach($slides->skip(1)->take(2) as $slide)
                    <a href="{{ data_get($slide, 'link_url', '#san-pham-moi') }}"><img src="{{ data_get($slide, 'image') }}" alt="{{ data_get($slide, 'alt', data_get($slide, 'title')) }}"><span><small>{{ data_get($slide, 'kicker') }}</small><strong>{{ data_get($slide, 'title') }}</strong></span></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="f404-section f404-categories xd-landing-block" id="danh-muc" data-f404-reveal data-landing-block-id="{{ data_get($categoriesBlock, 'id') }}" data-block-type="foot404_categories">
        @include('theme-foot404::partials.edit-button', ['block' => $categoriesBlock])
        <div class="f404-container f404-category-rail">
            @forelse($items($categoriesBlock) as $item)<a href="{{ data_get($item, 'url', '#') }}"><span><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'alt', data_get($item, 'title')) }}"></span><strong>{{ data_get($item, 'title') }}</strong></a>@empty<p>Danh mục đang được cập nhật.</p>@endforelse
        </div>
    </section>

    <section class="f404-section xd-landing-block" id="gia-tri" data-landing-block-id="{{ data_get($promoTrio, 'id') }}" data-block-type="foot404_promo_trio">
        @include('theme-foot404::partials.edit-button', ['block' => $promoTrio])
        <div class="f404-container f404-promo-grid f404-promo-grid--three">@foreach($items($promoTrio) as $item)<a href="{{ data_get($item, 'url', '#') }}" data-f404-reveal><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><small>{{ data_get($item, 'summary') }}</small><strong>{{ data_get($item, 'title') }}</strong><b>Khám phá</b></span></a>@endforeach</div>
    </section>

    <section class="f404-section xd-landing-block" id="san-pham-uu-dai" data-landing-block-id="{{ data_get($dealsBlock, 'id') }}" data-block-type="foot404_deals">
        @include('theme-foot404::partials.edit-button', ['block' => $dealsBlock])
        <div class="f404-container"><header class="f404-heading" data-f404-reveal><span>{{ data_get($dealsBlock, 'data.subtitle') }}</span><h2>{{ data_get($dealsBlock, 'data.title', 'Sản phẩm ưu đãi') }}</h2><p>{{ data_get($dealsBlock, 'data.description') }}</p><a href="{{ route('site.catalog.search') }}">{{ data_get($dealsBlock, 'data.button_label', 'Xem tất cả') }}</a></header><div class="f404-products f404-products--four">@foreach($items($dealsBlock) as $item)@include('theme-foot404::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section class="f404-section f404-section--tint xd-landing-block" id="san-pham-moi" data-landing-block-id="{{ data_get($newBlock, 'id') }}" data-block-type="foot404_new_products">
        @include('theme-foot404::partials.edit-button', ['block' => $newBlock])
        <div class="f404-container"><header class="f404-heading" data-f404-reveal><span>{{ data_get($newBlock, 'data.subtitle') }}</span><h2>{{ data_get($newBlock, 'data.title', 'Sản phẩm mới') }}</h2><a href="{{ route('site.catalog.search') }}">{{ data_get($newBlock, 'data.button_label', 'Xem tất cả') }}</a></header><div class="f404-feature-products"><a class="f404-feature-products__banner" href="#ban-chay" data-f404-reveal><img src="{{ data_get($newBlock, 'settings.feature_image', '/theme-demo/ec903/food-cruise.webp') }}" alt="{{ data_get($newBlock, 'data.title') }}"><span><small>{{ data_get($newBlock, 'data.subtitle') }}</small><strong>{{ data_get($newBlock, 'data.description') }}</strong><b>Khám phá <i class="fa-solid fa-arrow-right"></i></b></span></a><div class="f404-products f404-products--four">@foreach($items($newBlock) as $item)@include('theme-foot404::partials.product-card', ['item' => $item])@endforeach</div></div></div>
    </section>

    <section class="f404-section xd-landing-block" id="ma-uu-dai" data-landing-block-id="{{ data_get($couponBlock, 'id') }}" data-block-type="foot404_coupon">
        @include('theme-foot404::partials.edit-button', ['block' => $couponBlock])
        <div class="f404-container"><div class="f404-coupon" data-f404-reveal style="--f404-coupon-bg:url('{{ data_get($couponBlock, 'settings.background_image', '/theme-demo/ec903/food-source.png') }}')"><span><small>{{ data_get($couponBlock, 'data.subtitle') }}</small><h2>{{ data_get($couponBlock, 'data.title') }}</h2><p>{{ data_get($couponBlock, 'data.description') }}</p></span><button type="button" data-f404-copy="{{ data_get($couponBlock, 'settings.coupon_code', 'FOOT404') }}">{{ data_get($couponBlock, 'settings.coupon_code', 'FOOT404') }} <i class="fa-regular fa-copy"></i></button></div></div>
    </section>

    <section class="f404-section xd-landing-block" id="ban-chay" data-landing-block-id="{{ data_get($bestBlock, 'id') }}" data-block-type="foot404_best_sellers">
        @include('theme-foot404::partials.edit-button', ['block' => $bestBlock])
        <div class="f404-container"><header class="f404-heading" data-f404-reveal><span>{{ data_get($bestBlock, 'data.subtitle') }}</span><h2>{{ data_get($bestBlock, 'data.title', 'Sản phẩm bán chạy') }}</h2><p>{{ data_get($bestBlock, 'data.description') }}</p><a href="{{ route('site.catalog.search') }}">{{ data_get($bestBlock, 'data.button_label', 'Xem tất cả') }}</a></header><div class="f404-products f404-products--five">@foreach($items($bestBlock) as $item)@include('theme-foot404::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section class="f404-section xd-landing-block" id="thuong-hieu" data-landing-block-id="{{ data_get($promoDuo, 'id') }}" data-block-type="foot404_promo_duo">
        @include('theme-foot404::partials.edit-button', ['block' => $promoDuo])
        <div class="f404-container f404-promo-grid f404-promo-grid--two">@foreach($items($promoDuo) as $item)<a href="{{ data_get($item, 'url', '#') }}" data-f404-reveal><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><small>Chọn lựa an tâm</small><strong>{{ data_get($item, 'title') }}</strong><p>{{ data_get($item, 'summary') }}</p><b>Khám phá</b></span></a>@endforeach</div>
    </section>
</main>
@endsection
