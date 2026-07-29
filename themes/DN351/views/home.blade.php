@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $image = fn ($item, string $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $short = fn ($value, int $length = 130) => \Illuminate\Support\Str::limit(strip_tags((string) $value), $length);
    $money = function ($item, string $key = 'price') {
        $formatted = data_get($item, 'formatted_'.$key);
        if (filled($formatted)) return $formatted;
        $value = (float) data_get($item, $key, 0);
        return $value > 0 ? number_format($value, 0, ',', '.').' đ' : '';
    };
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) ($locale['code'] ?? ''), 'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? ''))])->filter(fn (array $locale): bool => $locale['code'] !== '')->values()->all() : [];
@endphp
@extends('theme-dn351::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($themeShellData ?? [], 'branding.company_name', 'Meatlers Market')))
@section('content')
<main class="dn351-home">
@foreach($blocks as $block)
    @php
        $type = data_get($block, 'block_type');
        $blockId = data_get($block, 'id');
        $blockItems = $items($block);
    @endphp
    @switch($type)
    @case('hero_slider')
        @php
            $slides = collect($block['dynamic_items'] ?? [])->filter()->values();
            if ($slides->isEmpty()) $slides = collect(data_get($block, 'data.content.slides', []))->filter()->values();
            if ($slides->isEmpty()) $slides = collect([['image' => '/theme-demo/dn351/hero-market.jpg']]);
        @endphp
        <section id="trang-chu" class="dn351-hero dn351-block" data-block-type="hero_slider" data-landing-block-id="{{ $blockId }}" data-dn351-slider data-autoplay="{{ data_get($block, 'settings.autoplay_ms', 6500) }}">
            @foreach($slides as $index => $slide)
                <div class="dn351-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-dn351-slide style="background-image:linear-gradient(90deg,rgba(3,5,8,.24),rgba(3,5,8,.06)),url('{{ $image($slide, '/theme-demo/dn351/hero-market.jpg') }}')"></div>
            @endforeach
            <div class="dn351-container dn351-hero__content" data-dn351-reveal="up">
                <p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Meatlers') }}</p>
                <h1>{{ data_get($block, 'data.title', 'Nhà cung cấp thực phẩm tươi tốt nhất thị trường') }}</h1>
                <p>{{ data_get($block, 'data.description', 'Nguồn thực phẩm minh bạch, tuyển chọn mỗi ngày và giao tươi tận nơi.') }}</p>
                <a class="dn351-btn" href="{{ data_get($block, 'settings.primary_url', '#danh-muc') }}">{{ data_get($block, 'data.button_label', 'Khám phá ngay') }} <i class="fa-solid fa-arrow-right-long"></i></a>
            </div>
            <div class="dn351-hero__dots">@foreach($slides as $index => $slide)<button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-dn351-dot aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('about_experience')
        <section id="gioi-thieu" class="dn351-section dn351-about dn351-block" data-block-type="about_experience" data-landing-block-id="{{ $blockId }}">
            <div class="dn351-container dn351-about__grid">
                <figure data-dn351-reveal="left"><img src="{{ data_get($block, 'media.images.0', '/theme-demo/dn351/about-butcher.jpg') }}" alt="Chuyên gia tuyển chọn thực phẩm"></figure>
                <div class="dn351-about__copy" data-dn351-reveal="up">
                    <p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Giới thiệu về công ty') }}</p>
                    <h2>{{ data_get($block, 'data.title', 'Chúng tôi cung cấp các loại sản phẩm tốt nhất') }}</h2>
                    <p>{{ data_get($block, 'data.description') }}</p>
                    <a class="dn351-btn" href="#san-pham">{{ data_get($block, 'data.button_label', 'Tìm hiểu ngay') }} <i class="fa-solid fa-arrow-right-long"></i></a>
                    <span class="dn351-stamp"><b>PREMIUM</b><small>QUALITY</small></span>
                </div>
                <figure data-dn351-reveal="right"><img src="{{ data_get($block, 'media.images.1', '/theme-demo/dn351/about-washing.jpg') }}" alt="Sơ chế rau củ tươi sạch"></figure>
            </div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('dn351_promo_mosaic')
        <section class="dn351-promos dn351-block" data-block-type="dn351_promo_mosaic" data-landing-block-id="{{ $blockId }}">
            <article class="dn351-promos__meat" style="background-image:linear-gradient(90deg,rgba(8,8,10,.92),rgba(8,8,10,.22)),url('{{ data_get($block, 'media.meat', '/theme-demo/dn351/category-meat.jpg') }}')" data-dn351-reveal="left">
                <div><h2>{{ data_get($block, 'data.title', 'Thịt chất lượng nhà hàng') }}</h2><p>{{ data_get($block, 'data.description', 'Giao hàng tươi ngon tận nhà.') }}</p><a class="dn351-btn" href="#san-pham">Mua ngay <i class="fa-solid fa-arrow-right-long"></i></a></div>
            </article>
            <article class="dn351-promos__fruit" style="background-image:linear-gradient(90deg,rgba(24,24,24,.84),rgba(24,24,24,.12)),url('{{ data_get($block, 'media.fruit', '/theme-demo/dn351/hero-market.jpg') }}')" data-dn351-reveal="right"><div><h3>Trái cây tươi nhập khẩu</h3><p>Tươi ngon theo mùa, hương vị tuyển chọn.</p><strong>499.000 VNĐ</strong></div></article>
            <article class="dn351-promos__store" style="background-image:url('{{ data_get($block, 'media.store', '/theme-demo/dn351/testimonial-butcher.jpg') }}')" data-dn351-reveal="right"><div><p class="dn351-script">Ghé thăm hôm nay</p><h3>Địa điểm tuyệt vời</h3><a href="{{ route('site.contact') }}">Liên hệ ngay <i class="fa-solid fa-arrow-right-long"></i></a></div></article>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('dn351_category_rail')
        <section id="danh-muc" class="dn351-section dn351-categories dn351-block" data-block-type="dn351_category_rail" data-landing-block-id="{{ $blockId }}">
            <div class="dn351-container">
                <header class="dn351-heading" data-dn351-reveal="up"><p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Khám phá ngay') }}</p><h2>{{ data_get($block, 'data.title', 'Mua sắm theo danh mục') }}</h2></header>
                <div class="dn351-category-grid">@foreach($blockItems->take(4) as $index => $item)<a href="{{ data_get($item, 'url', '#san-pham') }}" style="--delay:{{ $index * 80 }}ms" data-dn351-reveal="up"><span><img src="{{ $image($item, '/theme-demo/dn351/category-seafood.jpg') }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></span><strong>{{ data_get($item, 'title', data_get($item, 'name')) }}</strong></a>@endforeach</div>
            </div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('dn351_featured_split')
        <section class="dn351-featured dn351-block" data-block-type="dn351_featured_split" data-landing-block-id="{{ $blockId }}">
            <div class="dn351-featured__offer" style="background-image:linear-gradient(rgba(5,5,6,.45),rgba(5,5,6,.72)),url('{{ data_get($block, 'media.image', '/theme-demo/dn351/category-meat.jpg') }}')" data-dn351-reveal="left"><h2>{{ data_get($block, 'data.title', 'Giảm giá lên đến 15% cho toàn bộ sản phẩm') }}</h2><p>{{ data_get($block, 'data.description', 'Đặt mua thực phẩm tươi trực tuyến') }}</p><a class="dn351-btn" href="#san-pham">Mua ngay <i class="fa-solid fa-arrow-right-long"></i></a><b>15%</b></div>
            <div class="dn351-featured__products" data-dn351-reveal="right"><p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Lựa chọn của chúng tôi') }}</p><h2>Những sản phẩm yêu thích và bán chạy nhất trực tuyến</h2><div>@foreach($blockItems->take(2) as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/dn351/product-chicken.jpg') }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a><h3>{{ data_get($item, 'title', data_get($item, 'name')) }}</h3><strong>{{ $money($item) }}</strong></article>@endforeach</div></div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('dn351_product_grid')
        <section id="san-pham" class="dn351-section dn351-products dn351-block" data-block-type="dn351_product_grid" data-landing-block-id="{{ $blockId }}"><div class="dn351-container">
            <header class="dn351-heading" data-dn351-reveal="up"><p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Các loại thực phẩm') }}</p><h2>{{ data_get($block, 'data.title', 'Sản phẩm của chúng tôi') }}</h2></header>
            <div class="dn351-tabs"><button>Sản phẩm mới</button><button class="is-active">Sản phẩm tiêu dùng</button><button>Trái cây</button><button>Hải sản</button><button>Rau sạch</button></div>
            <div class="dn351-product-grid">@foreach($blockItems->take(8) as $index => $item)<article style="--delay:{{ ($index % 4) * 70 }}ms" data-dn351-reveal="up"><a class="dn351-product-card__image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/dn351/product-crab.jpg') }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}">@if((float)data_get($item,'original_price',0) > (float)data_get($item,'price',0))<span>-{{ max(1, round((1 - data_get($item,'price') / data_get($item,'original_price')) * 100)) }}%</span>@endif<i class="fa-solid fa-cart-shopping"></i></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h3><p><strong>{{ $money($item) }}</strong>@if(filled($money($item, 'original_price')))<del>{{ $money($item, 'original_price') }}</del>@endif</p></article>@endforeach</div>
        </div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('testimonials')
        @php $testimonial = $blockItems->first() ?? []; @endphp
        <section class="dn351-section dn351-testimonials dn351-block" data-block-type="testimonials" data-landing-block-id="{{ $blockId }}">
            <img class="dn351-testimonials__side is-left" src="{{ data_get($block, 'media.left', '/theme-demo/dn351/testimonial-butcher.jpg') }}" alt="Đối tác Meatlers" data-dn351-reveal="left">
            <div class="dn351-testimonials__copy" data-dn351-reveal="up"><p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Lời chứng thực') }}</p><h2>{{ data_get($block, 'data.title', 'Hãy nghe những gì khách hàng của chúng tôi nói') }}</h2><blockquote>{{ data_get($testimonial, 'quote', data_get($testimonial, 'summary', 'Thực phẩm luôn tươi mới, nguồn gốc rõ ràng và đội ngũ giao hàng rất tận tâm.')) }}</blockquote><div><img src="{{ $image($testimonial, '/theme-demo/dn351/testimonial-customer.jpg') }}" alt="{{ data_get($testimonial, 'name', 'Nguyễn Văn An') }}"><strong>{{ data_get($testimonial, 'name', data_get($testimonial, 'title', 'Nguyễn Văn An')) }}<small>{{ data_get($testimonial, 'role', 'Khách hàng thân thiết') }}</small></strong></div></div>
            <img class="dn351-testimonials__side is-right" src="{{ data_get($block, 'media.right', '/theme-demo/dn351/testimonial-customer.jpg') }}" alt="Khách hàng Meatlers" data-dn351-reveal="right">
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('latest_posts')
        <section id="tin-tuc" class="dn351-section dn351-posts dn351-block" data-block-type="latest_posts" data-landing-block-id="{{ $blockId }}"><div class="dn351-container"><header class="dn351-heading" data-dn351-reveal="up"><p class="dn351-script">{{ data_get($block, 'data.subtitle', 'Blog của chúng tôi') }}</p><h2>{{ data_get($block, 'data.title', 'Tin tức mới nhất của chúng tôi') }}</h2></header><div class="dn351-post-grid">@foreach($blockItems->take(3) as $index => $item)<article style="--delay:{{ $index * 90 }}ms" data-dn351-reveal="up"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/dn351/blog-squid-grilled.jpg') }}" alt="{{ data_get($item, 'title') }}"></a><div><h3>{{ data_get($item, 'title') }}</h3><p class="dn351-meta"><i class="fa-regular fa-calendar"></i>{{ data_get($item, 'date', '09/05/2026') }} <i class="fa-regular fa-eye"></i>{{ data_get($item, 'views', 91) }}</p><p>{{ $short(data_get($item, 'summary'), 120) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right-long"></i></a></div></article>@endforeach</div></div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('partner_logos')
        <section id="thu-vien" class="dn351-partners dn351-block" data-block-type="partner_logos" data-landing-block-id="{{ $blockId }}"><div class="dn351-container">@foreach($blockItems->take(7) as $index => $item)<a href="{{ data_get($item, 'url', '#') }}" style="--delay:{{ $index * 45 }}ms" data-dn351-reveal="up"><i class="{{ data_get($item, 'icon', 'fa-solid fa-award') }}"></i><strong>{{ data_get($item, 'title', 'PREMIUM') }}</strong><small>{{ data_get($item, 'summary', 'FRESH MARKET') }}</small></a>@endforeach</div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('newsletter_signup')
        <section class="dn351-newsletter dn351-block" data-block-type="newsletter_signup" data-landing-block-id="{{ $blockId }}" style="background-image:linear-gradient(90deg,rgba(8,8,9,.93),rgba(8,8,9,.78)),url('{{ data_get($block, 'media.image', '/theme-demo/dn351/hero-market.jpg') }}')"><div class="dn351-container" data-dn351-reveal="up"><h2>{{ data_get($block, 'data.title', 'Đăng ký để nhận cập nhật hàng tuần') }}</h2><p>{{ data_get($block, 'data.description', 'Nhận ưu đãi độc quyền, mẹo nấu ăn và thông tin thực phẩm mới nhất.') }}</p><form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="dn351-home"><input type="email" name="email" required placeholder="Địa chỉ email....."><button type="submit">Đăng ký <i class="fa-regular fa-paper-plane"></i></button></form></div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('footer_contact')
        <section class="dn351-block dn351-footer-marker" data-block-type="footer_contact" data-landing-block-id="{{ $blockId }}" aria-hidden="true">@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break
    @endswitch
@endforeach
</main>
@endsection

@if($canEditLanding)
    @push('scripts')
        @include('theme-xd0302::partials.scripts')
    @endpush
@endif
