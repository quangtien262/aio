@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $image = fn ($item, string $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $short = fn ($value, int $length = 140) => \Illuminate\Support\Str::limit(strip_tags((string) $value), $length);
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) ($locale['code'] ?? ''), 'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? ''))])->filter(fn (array $locale): bool => $locale['code'] !== '')->values()->all() : [];
@endphp
@extends('theme-dn350::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($themeShellData ?? [], 'branding.company_name', 'Prinash Cleaning')))
@section('content')
<main>
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
            if ($slides->isEmpty()) $slides = collect([['image' => '/theme-demo/dn350/hero-pressure-washing.webp']]);
        @endphp
        <section id="trang-chu" class="dn350-hero dn350-block" data-block-type="hero_slider" data-landing-block-id="{{ $blockId }}" data-dn350-slider data-autoplay="{{ data_get($block, 'settings.autoplay_ms', 6500) }}">
            @foreach($slides as $index => $slide)
                <div class="dn350-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-dn350-slide style="background-image:linear-gradient(90deg,rgba(4,12,38,.96) 0%,rgba(4,12,38,.73) 42%,rgba(4,12,38,.05) 74%),url('{{ $image($slide, '/theme-demo/dn350/hero-pressure-washing.webp') }}')"></div>
            @endforeach
            <div class="dn350-container dn350-hero__content" data-dn350-reveal="left">
                <p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Giải pháp vệ sinh chuyên nghiệp') }}</p>
                <h1>{{ data_get($block, 'data.title', 'Chúng tôi là lựa chọn tốt nhất cho bạn') }}</h1>
                <p>{{ data_get($block, 'data.description', 'Chi phí phải chăng, quy trình minh bạch và chất lượng làm sạch không đổi cho mọi gia đình, doanh nghiệp.') }}</p>
                <div class="dn350-actions"><a class="dn350-btn" href="{{ data_get($block, 'settings.primary_url', '#dich-vu') }}">{{ data_get($block, 'data.button_label', 'Khám phá thêm') }}</a><a class="dn350-btn is-light" href="{{ route('site.contact') }}">Liên hệ với chúng tôi</a></div>
            </div>
            <div class="dn350-hero__dots">@foreach($slides as $index => $slide)<button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-dn350-dot aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('about_experience')
        <section id="gioi-thieu" class="dn350-section dn350-block" data-block-type="about_experience" data-landing-block-id="{{ $blockId }}">
            <div class="dn350-container dn350-mission">
                <div class="dn350-mission__media" data-dn350-reveal="left"><img src="{{ data_get($block, 'media.images.0', '/theme-demo/dn350/mission-deck.webp') }}" alt="Vệ sinh sàn bằng máy phun áp lực"><img src="{{ data_get($block, 'media.images.1', '/theme-demo/dn350/mission-railing.webp') }}" alt="Vệ sinh lan can áp lực cao"></div>
                <div data-dn350-reveal="right"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Sứ mệnh của chúng tôi') }}</p><h2>{{ data_get($block, 'data.title', 'Mục tiêu chính của chúng tôi là vệ sinh bằng máy phun áp lực cao') }}</h2><p class="dn350-lead">{{ data_get($block, 'data.description') }}</p><div class="dn350-checks">@foreach($blockItems as $item)<p><i class="fa-solid fa-check"></i><span>{{ data_get($item, 'summary', data_get($item, 'title')) }}</span></p>@endforeach</div><a class="dn350-btn" href="#dich-vu">{{ data_get($block, 'data.button_label', 'Tìm hiểu ngay') }}</a><strong class="dn350-experience"><i class="fa-solid fa-award"></i> Hơn 10 năm kinh nghiệm</strong></div>
            </div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('featured_services')
        <section id="dich-vu" class="dn350-section dn350-soft dn350-block" data-block-type="featured_services" data-landing-block-id="{{ $blockId }}">
            <div class="dn350-container"><header class="dn350-heading" data-dn350-reveal="up"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Dịch vụ của chúng tôi') }}</p><h2>{{ data_get($block, 'data.title', 'Dịch vụ tốt nhất mà chúng tôi cung cấp') }}</h2></header>
            <div class="dn350-service-grid">@foreach($blockItems->take(6) as $index => $item)<article class="dn350-service-card" style="--delay:{{ $index * 70 }}ms" data-dn350-reveal="up"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/dn350/service-hourly.webp') }}" alt="{{ data_get($item, 'title') }}"></a><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ $short(data_get($item, 'summary'), 125) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right-long"></i></a></div></article>@endforeach</div></div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('featured_categories')
        <section id="ly-do" class="dn350-section dn350-reasons dn350-block" data-block-type="featured_categories" data-landing-block-id="{{ $blockId }}">
            <div class="dn350-container dn350-reasons__grid"><div data-dn350-reveal="left"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Tại sao nên chọn chúng tôi?') }}</p><h2>{{ data_get($block, 'data.title', 'Tại sao bạn nên chọn dịch vụ của chúng tôi?') }}</h2><p>{{ data_get($block, 'data.description') }}</p><div class="dn350-reason-list">@foreach($blockItems->take(4) as $index => $item)<article style="--delay:{{ $index * 80 }}ms" data-dn350-reveal="up"><i class="{{ data_get($item, 'icon', 'fa-regular fa-star') }}"></i><h3>{{ data_get($item, 'title') }}</h3></article>@endforeach</div></div><div class="dn350-reasons__image" data-dn350-reveal="right"><img src="{{ data_get($block, 'media.image', '/theme-demo/dn350/reasons-team.webp') }}" alt="Đội ngũ vệ sinh chuyên nghiệp"></div></div>
            @if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif
        </section>
        @break

    @case('testimonials')
        <section class="dn350-section dn350-block" data-block-type="testimonials" data-landing-block-id="{{ $blockId }}"><div class="dn350-container"><header class="dn350-heading is-left" data-dn350-reveal="up"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Lời chứng thực') }}</p><h2>{{ data_get($block, 'data.title', 'Mọi người đang nói gì') }}</h2></header><div class="dn350-testimonial-grid">@foreach($blockItems->take(3) as $index => $item)<article style="--delay:{{ $index * 100 }}ms" data-dn350-reveal="up"><i class="fa-solid fa-quote-right"></i><p>{{ data_get($item, 'quote', data_get($item, 'summary')) }}</p><div><img src="{{ $image($item, '/theme-demo/dn350/gallery-cleaner.webp') }}" alt="{{ data_get($item, 'name', data_get($item, 'title')) }}"><strong>{{ data_get($item, 'name', data_get($item, 'title')) }}<small>{{ data_get($item, 'role') }}</small></strong></div></article>@endforeach</div></div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('project_gallery')
        <section id="thu-vien" class="dn350-section dn350-gallery dn350-block" data-block-type="project_gallery" data-landing-block-id="{{ $blockId }}"><header class="dn350-heading" data-dn350-reveal="up"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Phòng trưng bày của chúng tôi') }}</p><h2>{{ data_get($block, 'data.title', 'Bộ sưu tập ảnh mới nhất') }}</h2></header><div class="dn350-gallery__row">@foreach($blockItems->take(5) as $index => $item)<a href="{{ data_get($item, 'url', '#') }}" style="--delay:{{ $index * 70 }}ms" data-dn350-reveal="scale"><img src="{{ $image($item, '/theme-demo/dn350/gallery-team.webp') }}" alt="{{ data_get($item, 'title', 'Dịch vụ vệ sinh') }}"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('latest_posts')
        <section id="tin-tuc" class="dn350-section dn350-block" data-block-type="latest_posts" data-landing-block-id="{{ $blockId }}"><div class="dn350-container"><header class="dn350-heading" data-dn350-reveal="up"><p class="dn350-kicker">{{ data_get($block, 'data.subtitle', 'Blog của chúng tôi') }}</p><h2>{{ data_get($block, 'data.title', 'Tin tức & Blog mới nhất') }}</h2></header><div class="dn350-post-grid">@foreach($blockItems->take(3) as $index => $item)<article style="--delay:{{ $index * 90 }}ms" data-dn350-reveal="up"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/dn350/gallery-kitchen.webp') }}" alt="{{ data_get($item, 'title') }}"></a><div><h3>{{ data_get($item, 'title') }}</h3><p class="dn350-meta"><i class="fa-regular fa-calendar"></i>{{ data_get($item, 'date', '29/06/2026') }} <i class="fa-regular fa-eye"></i>{{ data_get($item, 'views', 53) }}</p><p>{{ $short(data_get($item, 'summary'), 120) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right-long"></i></a></div></article>@endforeach</div></div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('newsletter_signup')
        <section class="dn350-newsletter dn350-block" data-block-type="newsletter_signup" data-landing-block-id="{{ $blockId }}"><div class="dn350-container"><h2>{{ data_get($block, 'data.title', 'Đừng bỏ lỡ các cập nhật của chúng tôi – hãy đăng ký ngay!') }}</h2><form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="dn350-home"><input type="email" name="email" required placeholder="Địa chỉ email....."><button type="submit">Đăng ký <i class="fa-solid fa-paper-plane"></i></button></form></div>@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
        @break

    @case('footer_contact')
        <section class="dn350-block dn350-footer-marker" data-block-type="footer_contact" data-landing-block-id="{{ $blockId }}" aria-hidden="true">@if($canEditLanding && $blockId)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $blockId }}">Sửa khối</button>@endif</section>
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
