@php
    $blocks = collect($landingBlocks ?? []);
    $get = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $image = fn ($item, string $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $short = fn ($value, int $length = 145) => \Illuminate\Support\Str::limit(strip_tags((string) $value), $length);
    $hero = $get('hero_slider');
    $about = $get('about_experience');
    $services = $get('featured_services');
    $glass = $get('featured_categories');
    $projects = $get('project_gallery');
    $styles = $get('content_showcase');
    $newsletter = $get('newsletter_signup');
    $testimonials = $get('testimonials');
    $news = $get('latest_posts');
    $contact = $get('landing_contact');
    $partners = $get('partner_logos');
    $living = '/theme-demo/dn302/dn302-living-room.png';
    $villa = '/theme-demo/dn302/dn302-villa.png';
    $bathroom = '/theme-demo/curated/home/bathroom/bathroom-02.jpg';
    $slides = collect($hero['dynamic_items'] ?? data_get($hero, 'data.content.slides', []));
    if ($slides->isEmpty()) {
        $slides = collect([
            ['title' => 'Thi công lắp đặt các loại cửa dân dụng', 'summary' => 'Cung cấp cửa sổ, cửa ra vào bằng nhôm kính an toàn, tiện nghi và thân thiện với môi trường.', 'image' => $living],
            ['title' => 'Không gian mở, sống trọn từng khoảnh khắc', 'summary' => 'Giải pháp nhôm kính cao cấp được thiết kế riêng cho phong cách sống hiện đại.', 'image' => $villa],
        ]);
    }
@endphp
@extends('theme-dn302::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? [], 'site_name', 'Website'))))
@section('content')
<main>
    <section class="dn-hero xd-landing-block" data-block-type="hero_slider" data-dn-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6500) }}">
        <div class="dn-container dn-hero-stage">
            <div class="dn-hero-copy" data-dn-reveal="left">
                <p class="dn-eyebrow">{{ data_get($hero, 'data.subtitle', 'Cung cấp giải pháp trọn gói') }}</p>
                <h1>{{ data_get($slides->first(), 'title', data_get($hero, 'data.title', 'Thi công lắp đặt các loại cửa dân dụng')) }}</h1>
                <p>{{ data_get($slides->first(), 'summary', data_get($hero, 'data.description')) }}</p>
                <a class="dn-btn" href="#gioi-thieu">{{ data_get($hero, 'data.button_label', 'Tìm hiểu ngay') }} <i class="fa-solid fa-arrow-right-long"></i></a>
            </div>
            <div class="dn-hero-media" data-dn-reveal="right" data-dn-parallax>
                @foreach($slides as $index => $slide)
                    <article class="dn-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-dn-slide>
                        <img src="{{ $image($slide, $living) }}" alt="{{ data_get($slide, 'title', 'Janelas Windows & Doors') }}">
                    </article>
                @endforeach
                <div class="dn-hero-dots">
                    @foreach($slides as $index => $slide)
                        <button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-dn-dot aria-label="Slide {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="gioi-thieu" class="dn-section xd-landing-block" data-block-type="about_experience">
        <div class="dn-container dn-about-grid">
            <div class="dn-about-media" data-dn-reveal="left"><img src="{{ data_get($about, 'media.image', $living) }}" alt="Không gian hiện đại Janelas"></div>
            <div class="dn-about-copy" data-dn-reveal="right">
                <p class="dn-eyebrow">{{ data_get($about, 'data.subtitle', 'Giới thiệu') }}</p>
                <h2 class="dn-title">{{ data_get($about, 'data.title', 'Tạo nên không gian hiện đại cho ngôi nhà của bạn') }}</h2>
                <p>{{ data_get($about, 'data.description', 'Với nhiều năm kinh nghiệm, chúng tôi luôn tôn trọng khách hàng, giữ vững uy tín và mang đến các giải pháp nhôm kính bền vững.') }}</p>
                <div class="dn-values">
                    @foreach($items($about) as $index => $item)
                        <article class="dn-value" style="--dn-delay:{{ $index * 90 }}ms" data-dn-reveal="up"><i class="fa-solid fa-check"></i><span>{{ data_get($item, 'title') }}</span></article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="dich-vu" class="dn-section dn-services xd-landing-block" data-block-type="featured_services">
        <div class="dn-container">
            <div class="dn-services-head">
                <header data-dn-reveal="left"><p class="dn-eyebrow">{{ data_get($services, 'data.subtitle', 'Nổi bật') }}</p><h2 class="dn-title">{{ data_get($services, 'data.title', 'Dịch vụ của chúng tôi') }}</h2></header>
                <p data-dn-reveal="right">{{ data_get($services, 'data.description', 'Chất lượng là nền tảng thành công. Chúng tôi cam kết mang đến sản phẩm chuẩn xác, dịch vụ chuyên nghiệp và chính sách bảo hành dài hạn.') }}</p>
            </div>
            <div class="dn-service-grid">
                @foreach($items($services) as $index => $item)
                    <article class="dn-card" style="--dn-delay:{{ $index * 110 }}ms" data-dn-reveal="up">
                        <a class="dn-service-image" href="{{ data_get($item, 'url', '#lien-he') }}"><img src="{{ $image($item, $index === 1 ? $villa : $living) }}" alt="{{ data_get($item, 'title') }}"></a>
                        <div class="dn-service-body"><h3>{{ data_get($item, 'title') }}</h3><p>{{ $short(data_get($item, 'summary'), 175) }}</p><a class="dn-round-link" href="{{ data_get($item, 'url', '#lien-he') }}" aria-label="Xem {{ data_get($item, 'title') }}"><i class="fa-solid fa-arrow-right-long"></i></a></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="dn-section xd-landing-block" data-block-type="featured_categories">
        <div class="dn-container dn-glass-grid">
            <img class="dn-glass-image" src="{{ data_get($glass, 'media.image', $living) }}" alt="Cửa kính cao cấp" data-dn-reveal="left">
            <div class="dn-glass-copy" data-dn-reveal="right">
                <p class="dn-eyebrow">{{ data_get($glass, 'data.subtitle', 'Khám phá') }}</p>
                <h2 class="dn-title">{{ data_get($glass, 'data.title', 'Cửa kính cao cấp') }}</h2>
                <p>{{ data_get($glass, 'data.description', 'Sản xuất từ nhôm nhập khẩu, kính cường lực, gioăng cao su và bộ phụ kiện kim khí đồng bộ.') }}</p>
                <div class="dn-feature-table">
                    @foreach($items($glass) as $index => $item)
                        <article class="dn-feature" style="--dn-delay:{{ $index * 70 }}ms" data-dn-reveal="scale"><i class="{{ data_get($item, 'icon', 'fa-regular fa-window-maximize') }}"></i><span>{{ data_get($item, 'title') }}</span></article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="du-an" class="dn-section dn-projects xd-landing-block" data-block-type="project_gallery">
        <div class="dn-container">
            <header class="dn-heading center" data-dn-reveal="up"><p class="dn-eyebrow">{{ data_get($projects, 'data.subtitle', 'Dự án') }}</p><h2 class="dn-title">{{ data_get($projects, 'data.title', 'Hoàn thành') }}</h2></header>
            <div class="dn-project-tabs" data-dn-reveal="up"><button class="is-active" type="button" data-dn-project-tab="all">Tất cả</button><button type="button" data-dn-project-tab="villa">Biệt thự - Nhà phố</button><button type="button" data-dn-project-tab="store">Cửa hàng</button><button type="button" data-dn-project-tab="office">Tòa nhà văn phòng</button></div>
            <div class="dn-project-grid">
                @foreach($items($projects)->take(4) as $index => $item)
                    @php $categories = ['villa', 'office', 'store', 'villa']; @endphp
                    <a class="dn-project" href="{{ data_get($item, 'url', '#') }}" data-title="{{ data_get($item, 'title') }}" data-category="{{ $categories[$index] ?? 'villa' }}" data-dn-project data-dn-reveal="scale" style="--dn-delay:{{ $index * 90 }}ms"><img src="{{ $image($item, $index % 2 ? $living : $villa) }}" alt="{{ data_get($item, 'title') }}"></a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="dn-section xd-landing-block" data-block-type="content_showcase">
        <div class="dn-container">
            <header class="dn-heading center" data-dn-reveal="up"><p class="dn-eyebrow">{{ data_get($styles, 'data.subtitle', 'Lắp đặt') }}</p><h2 class="dn-title">{{ data_get($styles, 'data.title', 'Các kiểu cửa chính') }}</h2><p>{{ data_get($styles, 'data.description', 'Cửa ra vào, cửa sổ, cửa cuốn, cửa kéo, mái tôn, mái che và mái hiên di động') }}</p></header>
            <div class="dn-styles-grid">
                <div class="dn-style-cards">
                    @foreach($items($styles) as $index => $item)
                        <article class="dn-style-card" style="--dn-delay:{{ $index * 80 }}ms" data-dn-reveal="left"><i class="{{ data_get($item, 'icon', 'fa-regular fa-window-maximize') }}"></i><h3>{{ data_get($item, 'title') }}</h3></article>
                    @endforeach
                </div>
                <div class="dn-styles-image" data-dn-reveal="right"><img src="{{ data_get($styles, 'media.image', $living) }}" alt="Các kiểu cửa chính"><a class="dn-btn" href="{{ route('site.services.index') }}">@themeT('DN302.common.view_more', 'Xem thêm') <i class="fa-solid fa-arrow-right-long"></i></a></div>
            </div>
        </div>
    </section>

    <section class="dn-newsletter-wrap xd-landing-block" data-block-type="newsletter_signup">
        <div class="dn-container dn-newsletter" data-dn-reveal="up">
            <div><p class="dn-eyebrow">{{ data_get($newsletter, 'data.subtitle', 'Đăng ký') }}</p><h2 class="dn-title">{{ data_get($newsletter, 'data.title', 'Đăng ký nhận bản tin và tin tức cập nhật mới nhất') }}</h2></div>
            <form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="dn302-home"><input type="email" name="email" required placeholder="Địa chỉ email....."><button type="submit">Đăng ký</button></form>
        </div>
    </section>

    <section class="dn-section dn-testimonials xd-landing-block" data-block-type="testimonials">
        <div class="dn-container">
            <div class="dn-testimonial-head"><header data-dn-reveal="left"><p class="dn-eyebrow">{{ data_get($testimonials, 'data.subtitle', 'Nhận xét từ') }}</p><h2 class="dn-title">{{ data_get($testimonials, 'data.title', 'Khách hàng') }}</h2></header><p data-dn-reveal="right">{{ data_get($testimonials, 'data.description', 'Cửa nhôm kính đẹp cần vật liệu đạt chuẩn, hệ gioăng chất lượng, phụ kiện đồng bộ chính xác và nhân viên lắp đặt có quy trình.') }}</p></div>
            <div class="dn-testimonial-layout">
                <div class="dn-stats" data-dn-reveal="left"><div class="dn-stat"><i class="fa-solid fa-award"></i><p><strong>10</strong><span>Năm kinh nghiệm</span></p></div><div class="dn-stat"><i class="fa-solid fa-users"></i><p><strong>100%</strong><span>Sự hài lòng</span></p></div><div class="dn-stat"><i class="fa-solid fa-heart"></i><p><strong>95K</strong><span>Khách hàng yêu thích</span></p></div></div>
                <div class="dn-quotes">
                    @foreach($items($testimonials)->take(2) as $index => $item)
                        <article class="dn-quote" style="--dn-delay:{{ $index * 120 }}ms" data-dn-reveal="right"><div class="dn-quote-top"><img src="{{ $image($item, $villa) }}" alt="{{ data_get($item, 'name', data_get($item, 'title')) }}"><div><h3>{{ data_get($item, 'name', data_get($item, 'title')) }}</h3><span>{{ data_get($item, 'role') }}</span></div></div><p>“{{ data_get($item, 'quote', data_get($item, 'summary')) }}”</p></article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="tin-tuc" class="dn-section xd-landing-block" data-block-type="latest_posts">
        <div class="dn-container">
            <div class="dn-heading dn-news-head" data-dn-reveal="up"><header><p class="dn-eyebrow">{{ data_get($news, 'data.subtitle', 'Tin tức cập nhật') }}</p><h2 class="dn-title">{{ data_get($news, 'data.title', 'Kiến thức & Kinh nghiệm') }}</h2></header><a class="dn-btn" href="{{ route('site.blog.index') }}">@themeT('DN302.common.view_more', 'Xem thêm')</a></div>
            <div class="dn-news-grid">
                @foreach($items($news)->take(3) as $index => $item)
                    <article class="dn-news-card" style="--dn-delay:{{ $index * 110 }}ms" data-dn-reveal="up"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, $index === 1 ? $living : $villa) }}" alt="{{ data_get($item, 'title') }}"></a><time class="dn-news-date">{{ data_get($item, 'date', '15/04/2022') }}</time><div class="dn-news-body"><h3>{{ data_get($item, 'title') }}</h3><p>{{ $short(data_get($item, 'summary')) }}</p><a href="{{ data_get($item, 'url', '#') }}"><i class="fa-solid fa-circle-arrow-right"></i> @themeT('DN302.common.read_more', 'Đọc thêm')</a></div></article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="lien-he" class="dn-section dn-contact xd-landing-block" data-block-type="landing_contact">
        <div class="dn-container">
            <header class="dn-heading center" data-dn-reveal="up"><p class="dn-eyebrow">{{ data_get($contact, 'data.subtitle', 'Liên hệ') }}</p><h2 class="dn-title">{{ data_get($contact, 'data.title', 'Đăng ký tư vấn dịch vụ') }}</h2></header>
            <div class="dn-contact-grid">
                <aside class="dn-contact-info" data-dn-reveal="left"><img src="{{ data_get($contact, 'media.image', $living) }}" alt="Liên hệ Janelas"><div class="dn-contact-panel"><p><i class="fa-solid fa-clock"></i><span>Thời gian làm việc<br><strong>Thứ 2 - thứ 7 (9:00 - 17:00)</strong></span></p><p><i class="fa-solid fa-phone"></i><span>Hotline<br><strong>1900 9477</strong></span></p></div></aside>
                <div class="dn-contact-form" data-dn-reveal="right"><form method="POST" action="{{ route('site.contact.submit') }}">@csrf<input type="hidden" name="source" value="dn302-landing"><input name="name" required placeholder="* Họ và tên"><input type="email" name="email" required placeholder="* Email"><input name="phone" required placeholder="* Số điện thoại"><input name="address" placeholder="Địa chỉ"><textarea name="message" required minlength="10" placeholder="* Nội dung"></textarea><button class="dn-btn" type="submit">@themeT('DN302.common.send', 'Gửi yêu cầu')</button></form></div>
            </div>
        </div>
    </section>

    <section class="dn-partners xd-landing-block" data-block-type="partner_logos">
        <div class="dn-container dn-partner-track">
            @foreach($items($partners)->take(10) as $index => $item)
                <a class="dn-partner" href="{{ data_get($item, 'url', '#') }}" style="--dn-delay:{{ $index * 60 }}ms" data-dn-reveal="scale">@if($image($item))<img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}">@else<span>{{ data_get($item, 'title') }}</span>@endif</a>
            @endforeach
        </div>
    </section>
</main>
@endsection
