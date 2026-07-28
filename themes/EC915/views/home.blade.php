@extends('theme-ec915::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'ND Interior')))
@section('content')
@php
    $blocks = collect($landingBlocks ?? [])->values();
    $get = fn (string $type): array => (array) ($blocks->firstWhere('block_type', $type) ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $hero = $get('hero_slider'); $about = $get('ec915_about'); $categories = $get('ec915_room_categories');
    $best = $get('ec915_best_sellers'); $contact = $get('ec915_contact_banner'); $process = $get('ec915_process');
    $reasons = $get('ec915_reasons'); $faq = $get('ec915_faq'); $testimonials = $get('ec915_testimonials');
    $news = $get('ec915_latest_posts'); $footer = $get('ec915_footer');
    $slides = collect($hero['dynamic_items'] ?? [])->filter()->values();
    if ($slides->isEmpty()) $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    $hotline = trim((string) data_get($siteProfile ?? [], 'branding.support_hotline', '')) ?: '1900 6750';
@endphp
<main class="ec15-main">
    <section class="ec15-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        <div class="ec15-hero-slider" data-ec15-slider>
            @forelse($slides as $index => $slide)<article class="{{ $index === 0 ? 'is-active' : '' }}" data-ec15-slide><img src="{{ data_get($slide, 'image', data_get($slide, 'image_url', '/theme-demo/ec915/hero-interior.webp')) }}" alt="{{ data_get($slide, 'title') }}"><div class="ec15-hero-shade"></div><div class="ec15-hero-copy"><span>{{ data_get($slide, 'badge', 'CHÀO MỪNG BẠN ĐẾN VỚI CHÚNG TÔI') }}</span><h1>{{ data_get($slide, 'title', 'Chuyên thi công & cung cấp sản phẩm nội thất cao cấp') }}</h1><p>{{ data_get($slide, 'summary', 'Kiến tạo không gian sống và làm việc hoàn hảo bằng tư duy thiết kế tinh tế và chất lượng thi công bền vững.') }}</p><a href="#gioi-thieu">Khám phá dự án <i class="fa-solid fa-arrow-down"></i></a></div></article>
            @empty<article class="is-active" data-ec15-slide><img src="/theme-demo/ec915/hero-interior.webp" alt="Nội thất cao cấp"><div class="ec15-hero-shade"></div><div class="ec15-hero-copy"><span>CHÀO MỪNG BẠN ĐẾN VỚI CHÚNG TÔI</span><h1>Chuyên thi công & cung cấp sản phẩm nội thất cao cấp</h1></div></article>@endforelse
            <button class="ec15-hero-arrow prev" data-ec15-prev><i class="fa-solid fa-chevron-left"></i></button><button class="ec15-hero-arrow next" data-ec15-next><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </section>

    <section id="gioi-thieu" class="ec15-section ec15-about xd-landing-block" data-landing-block-id="{{ data_get($about, 'id') }}" data-block-type="ec915_about" data-ec15-reveal>
        <div class="ec15-container ec15-about-layout"><div data-ec15-motion="left"><span class="ec15-eyebrow">VỀ CHÚNG TÔI</span><h2>{{ data_get($about, 'data.title', 'Giải pháp nội thất hoàn hảo cho không gian của bạn') }}</h2><p>{{ data_get($about, 'data.summary', 'ND Interior chuyên thiết kế, thi công nội thất trọn gói và cung cấp sản phẩm cao cấp cho nhà ở, căn hộ, văn phòng và showroom.') }}</p><div class="ec15-stats">@foreach($items($about) as $item)<article data-ec15-stagger><b>{{ data_get($item, 'value') }}</b><span>{{ data_get($item, 'title') }}</span></article>@endforeach</div></div><figure data-ec15-motion="right"><img src="{{ data_get($about, 'media.image', '/theme-demo/ec915/room-living-room.webp') }}" alt="Không gian nội thất"><i class="fa-solid fa-arrow-up-right"></i></figure></div>
    </section>

    <section id="danh-muc" class="ec15-section ec15-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="ec915_room_categories" data-ec15-reveal>
        <div class="ec15-container"><div class="ec15-room-grid">@foreach($items($categories) as $index => $item)<a class="item-{{ $index + 1 }}" href="{{ data_get($item, 'url', '#ban-chay') }}" data-ec15-stagger><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'summary') }}</small><em>Xem tất cả <i class="fa-solid fa-arrow-up-right"></i></em></span></a>@endforeach</div></div>
    </section>

    <section id="ban-chay" class="ec15-section xd-landing-block" data-landing-block-id="{{ data_get($best, 'id') }}" data-block-type="ec915_best_sellers" data-ec15-reveal>
        <div class="ec15-container"><div class="ec15-heading"><span>SẢN PHẨM CAO CẤP</span><h2>{{ data_get($best, 'data.title', 'Sản phẩm bán chạy') }}</h2></div><div class="ec15-product-grid">@foreach($items($best) as $item)@include('theme-ec915::partials.product-card', ['item' => $item])@endforeach</div></div>
    </section>

    <section class="ec15-contact-banner xd-landing-block" data-landing-block-id="{{ data_get($contact, 'id') }}" data-block-type="ec915_contact_banner" data-ec15-reveal>
        <img src="/theme-demo/ec915/contact-bedroom.webp" alt="Tư vấn nội thất"><div class="ec15-container"><article data-ec15-motion="scale"><div><h2>{{ data_get($contact, 'data.title', 'Liên hệ để được tư vấn') }}</h2><p>{{ data_get($contact, 'data.summary', 'Đội ngũ chuyên gia luôn sẵn sàng đồng hành cùng không gian của bạn.') }}</p></div><strong><i class="fa-solid fa-headset"></i>{{ $hotline }}</strong><a href="{{ route('site.contact') }}">Liên hệ ngay</a></article></div>
    </section>

    <section id="quy-trinh" class="ec15-section xd-landing-block" data-landing-block-id="{{ data_get($process, 'id') }}" data-block-type="ec915_process" data-ec15-reveal>
        <div class="ec15-container"><div class="ec15-heading"><span>QUY TRÌNH LÀM VIỆC</span><h2>{{ data_get($process, 'data.title', 'Cam kết chất lượng từ ND Interior') }}</h2></div><div class="ec15-process">@foreach($items($process) as $index => $item)<article data-ec15-stagger><span>{{ $index + 1 }}</span><i class="fa-solid {{ data_get($item, 'icon', 'fa-clipboard-check') }}"></i><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div></div>
    </section>

    <section class="ec15-section ec15-reasons xd-landing-block" data-landing-block-id="{{ data_get($reasons, 'id') }}" data-block-type="ec915_reasons" data-ec15-reveal>
        <div class="ec15-container ec15-reasons-layout"><div data-ec15-motion="left"><span class="ec15-eyebrow">VÌ SAO CHỌN ND INTERIOR?</span><h2>{{ data_get($reasons, 'data.title', 'Luôn ưu tiên sự hài lòng của khách hàng') }}</h2><p>{{ data_get($reasons, 'data.summary') }}</p>@foreach($items($reasons) as $item)<article data-ec15-stagger><i class="fa-solid fa-circle-check"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach</div><figure data-ec15-motion="right"><img src="/theme-demo/ec915/room-office.webp" alt="Nội thất tinh tế"></figure></div>
    </section>

    <section class="ec15-section ec15-faq xd-landing-block" data-landing-block-id="{{ data_get($faq, 'id') }}" data-block-type="ec915_faq" data-ec15-reveal>
        <div class="ec15-container ec15-faq-layout"><div data-ec15-motion="left"><span class="ec15-eyebrow">FAQ'S</span><h2>{{ data_get($faq, 'data.title', 'Câu hỏi thường gặp?') }}</h2><p>{{ data_get($faq, 'data.summary') }}</p><a href="{{ route('site.contact') }}">Gửi câu hỏi</a></div><div class="ec15-accordion" data-ec15-motion="right">@foreach($items($faq) as $index => $item)<article class="{{ $index === 0 ? 'is-open' : '' }}" data-ec15-accordion><button><i>?</i><b>{{ data_get($item, 'title') }}</b><span class="fa-solid fa-chevron-down"></span></button><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div></div>
    </section>

    <section class="ec15-section ec15-testimonials xd-landing-block" data-landing-block-id="{{ data_get($testimonials, 'id') }}" data-block-type="ec915_testimonials" data-ec15-reveal>
        <div class="ec15-container"><div class="ec15-heading"><span>Ý KIẾN KHÁCH HÀNG</span><h2>{{ data_get($testimonials, 'data.title', 'Khách hàng nói gì về chúng tôi?') }}</h2></div><div class="ec15-testimonial-grid">@foreach($items($testimonials) as $item)<article data-ec15-stagger><div class="ec15-stars">★★★★★</div><p>{{ data_get($item, 'summary') }}</p><footer><span>{{ mb_substr((string) data_get($item, 'title'), 0, 1) }}</span><div><b>{{ data_get($item, 'title') }}</b><small>{{ data_get($item, 'role') }}</small></div><i class="fa-solid fa-quote-right"></i></footer></article>@endforeach</div></div>
    </section>

    <section class="ec15-section ec15-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="ec915_latest_posts" data-ec15-reveal>
        <div class="ec15-container"><div class="ec15-heading"><span>BLOG ND INTERIOR</span><h2>{{ data_get($news, 'data.title', 'Tin tức và xu hướng nội thất!') }}</h2></div><div class="ec15-news-grid">@foreach($items($news) as $item)<article data-ec15-stagger><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url')) }}" alt="{{ data_get($item, 'title') }}"></a><small><i class="fa-regular fa-calendar"></i>{{ data_get($item, 'date', now()->format('d/m/Y')) }}</small><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt')) }}</p></article>@endforeach</div></div>
    </section>
    <section hidden class="xd-landing-block" data-landing-block-id="{{ data_get($footer, 'id') }}" data-block-type="ec915_footer"></section>
</main>
@endsection
