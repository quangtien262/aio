@extends('theme-ec903::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'EC903')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = fn (array $block) => collect($block['dynamic_items'] ?? [])->filter()->values()->whenEmpty(fn ($list) => $list->push(...collect(data_get($block, 'data.content.items', []))->filter()->values()->all()));
    $image = fn ($item, string $fallback) => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $hero = $get('hero_slider');
    $categories = $get('ec903_category_rail');
    $featured = $get('ec903_featured_deals');
    $food = $get('ec903_food_deals');
    $vegan = $get('ec903_vegetarian_deals');
    $beauty = $get('ec903_beauty_deals');
    $travel = $get('ec903_travel_deals');
    $newsletter = $get('ec903_newsletter');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $promos = collect(data_get($hero, 'data.content.promos', []))->filter()->values();
    $brands = collect(data_get($hero, 'data.content.brands', []))->filter()->values();
@endphp
<main class="ec93-main">
    <section class="ec93-hero-shell ec93-container">
        <aside class="ec93-category-rail xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec903_category_rail">
            @foreach($items($categories) as $index => $item)<a class="{{ $index === 0 ? 'is-hot' : '' }}" href="{{ data_get($item, 'url', '#') }}"><i class="{{ data_get($item, 'icon', 'fa-solid fa-ticket') }}"></i><span>{{ data_get($item, 'title') }}</span><i class="fa-solid fa-chevron-right"></i></a>@endforeach
        </aside>
        <section class="ec93-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-ec93-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 5600) }}">
            <div class="ec93-hero-main">@forelse($slides as $index => $slide)<article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec93-slide><img src="{{ $image($slide, '/theme-demo/ec903/hero-marketplace.webp') }}" alt="{{ data_get($slide, 'title') }}"><div><small>{{ data_get($slide, 'badge', 'DEAL ĐỘC QUYỀN') }}</small><h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1><p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p><strong>{{ data_get($slide, 'price_label', 'Chỉ từ 99.000đ') }}</strong><a href="{{ data_get($slide, 'link_url', '#deal-noi-bat') }}">{{ data_get($slide, 'button_label', 'Đặt ngay') }}</a></div></article>@empty<article class="is-active" data-ec93-slide><img src="/theme-demo/ec903/hero-marketplace.webp" alt="Deal dịch vụ"></article>@endforelse<button data-ec93-prev class="ec93-arrow ec93-prev"><i class="fa-solid fa-angle-left"></i></button><button data-ec93-next class="ec93-arrow ec93-next"><i class="fa-solid fa-angle-right"></i></button></div>
            <div class="ec93-brands">@foreach($brands as $brand)<span><i class="{{ data_get($brand, 'icon', 'fa-solid fa-star') }}"></i>{{ data_get($brand, 'title') }}</span>@endforeach</div>
        </section>
        <aside class="ec93-promo-stack">@foreach($promos as $item)<a href="{{ data_get($item, 'url', '#deal-noi-bat') }}"><img src="{{ $image($item, '/theme-demo/ec903/promo-dining.webp') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small></span></a>@endforeach</aside>
    </section>

    @include('theme-ec903::partials.deal-section', ['block' => $featured, 'blockType' => 'ec903_featured_deals', 'fallbackTitle' => 'Deal nổi bật', 'theme' => 'red', 'sectionId' => 'deal-noi-bat'])
    @include('theme-ec903::partials.deal-section', ['block' => $food, 'blockType' => 'ec903_food_deals', 'fallbackTitle' => 'Ẩm thực', 'theme' => 'lime', 'sectionId' => 'am-thuc', 'icon' => 'fa-solid fa-utensils'])
    @include('theme-ec903::partials.deal-section', ['block' => $vegan, 'blockType' => 'ec903_vegetarian_deals', 'fallbackTitle' => 'Ẩm thực chay', 'theme' => 'green', 'sectionId' => 'am-thuc-chay', 'icon' => 'fa-solid fa-leaf'])
    @include('theme-ec903::partials.deal-section', ['block' => $beauty, 'blockType' => 'ec903_beauty_deals', 'fallbackTitle' => 'Spa & Làm đẹp', 'theme' => 'rose', 'sectionId' => 'lam-dep', 'icon' => 'fa-solid fa-spa'])
    @include('theme-ec903::partials.deal-section', ['block' => $travel, 'blockType' => 'ec903_travel_deals', 'fallbackTitle' => 'Du lịch & Giải trí', 'theme' => 'blue', 'sectionId' => 'du-lich', 'icon' => 'fa-solid fa-plane'])

    <section id="newsletter" class="ec93-newsletter xd-landing-block" data-landing-block-id="{{ data_get($newsletter, 'id') }}" data-block-type="ec903_newsletter"><div class="ec93-container"><div><i class="fa-regular fa-envelope-open"></i><span><b>{{ data_get($newsletter, 'data.title', 'Nhận deal tốt mỗi tuần') }}</b><small>{{ data_get($newsletter, 'data.description', 'Ưu đãi ẩm thực, làm đẹp và du lịch được chọn lọc.') }}</small></span></div><form><input type="email" placeholder="Email của bạn"><button>Đăng ký</button></form></div></section>
</main>
@endsection
