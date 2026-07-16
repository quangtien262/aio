@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = [];
    $block = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block): array {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic->all() : collect(data_get($block, 'data.content.items', []))->filter()->values()->all();
    };
    $hero = $block('hero_slider');
    $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    if ($slides->isEmpty()) $slides = collect($hero['dynamic_items'] ?? []);
    if ($slides->isEmpty()) $slides = collect([['title' => 'Khong gian dep, chat luong ben vung', 'summary' => 'Thiet ke va thi cong noi that cho nhung ngoi nha mang dau an rieng.', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2200&q=90', 'button_label' => 'Kham pha du an', 'link_url' => '#du-an']]);
    $about = $block('about_experience');
    $showcase = $block('content_mosaic');
    $projects = $block('project_gallery');
    $services = $block('featured_services');
    $testimonials = $block('testimonials');
    $posts = $block('bizmax_latest_posts');
    $partners = $block('partner_logos');
    $stats = $block('featured_categories');
    $showcaseItems = $items($showcase); $projectItems = $items($projects); $serviceItems = $items($services);
    $testimonialItems = $items($testimonials); $postItems = $items($posts); $partnerItems = $items($partners); $statItems = $items($stats);
    $aboutImage = data_get($about, 'media.image', data_get($about, 'data.content.image', 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1200&q=90'));
    $limit = fn ($text, $length = 130) => \Illuminate\Support\Str::limit(strip_tags((string) $text), $length);
@endphp

@extends('theme-nt501::layout')

@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'NT501 Interior Studio')))

@section('content')
<main class="nt-main">
    <section class="nt-hero" aria-label="{{ data_get($hero, 'data.title', 'NT501 Interior Studio') }}">
        @foreach ($slides as $index => $slide)
            <article class="nt-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-nt-hero-slide>
                <img src="{{ data_get($slide, 'image') }}" alt="{{ data_get($slide, 'title', '') }}">
                <div class="nt-hero__shade"></div>
                <div class="nt-container nt-hero__content"><p>{{ data_get($hero, 'data.subtitle', 'Thiet ke va thi cong noi that') }}</p><h1>{{ data_get($slide, 'title') }}</h1><div>{{ data_get($slide, 'summary', data_get($hero, 'data.description', '')) }}</div><a class="nt-button" href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#du-an')) }}">{{ data_get($slide, 'button_label', 'Kham pha them') }}</a></div>
            </article>
        @endforeach
    </section>

    <section id="gioi-thieu" class="nt-about nt-section"><div class="nt-container nt-about__grid"><div class="nt-about__copy"><p class="nt-eyebrow">{{ data_get($about, 'data.subtitle', 'Gioi thieu ve NT501') }}</p><h2>{{ data_get($about, 'data.title', 'Kien tao khong gian song day cam hung') }}</h2><div>{!! nl2br(e(data_get($about, 'data.description', 'Chung toi ket hop tu duy thiet ke tinh te, vat lieu chat luong va quy trinh thi cong ky luong de bien moi y tuong thanh mot khong gian dang song.'))) !!}</div><a class="nt-text-link" href="{{ data_get($about, 'settings.cta_url', '#dich-vu') }}">{{ data_get($about, 'data.button_label', 'Xem dich vu') }}</a></div><div class="nt-about__media"><img src="{{ $aboutImage }}" alt="{{ data_get($about, 'data.title', 'NT501') }}"><span>10+<small>nam dong hanh</small></span></div></div></section>

    <section class="nt-section nt-showcase"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($showcase, 'data.subtitle', 'Khong gian tieu bieu') }}</p><h2>{{ data_get($showcase, 'data.title', 'Giai phap cho moi phong cach song') }}</h2></div><div class="nt-showcase__grid">@forelse ($showcaseItems as $item)<article class="nt-showcase__card"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><a href="{{ data_get($item, 'url', '#') }}"><h3>{{ data_get($item, 'title', 'Thiet ke noi that') }}</h3><span>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 95) }}</span></a></article>@empty <p>Dang cap nhat du an.</p>@endforelse <aside class="nt-hours"><p>Tu van cung NT501</p><h3>Dat lich trao doi</h3><strong>Thu hai - Thu bay<br>08:30 - 18:00</strong><a class="nt-button" href="#lien-he">Nhan bao gia</a></aside></div></div></section>

    <section id="du-an" class="nt-section nt-projects"><div class="nt-container"><div class="nt-section-head nt-section-head--row"><div><p class="nt-eyebrow">{{ data_get($projects, 'data.subtitle', 'Du an da thuc hien') }}</p><h2>{{ data_get($projects, 'data.title', 'Nhung cong trinh noi bat') }}</h2></div><a class="nt-text-link" href="{{ data_get($projects, 'settings.cta_url', '#du-an') }}">Xem tat ca</a></div><div class="nt-rail">@forelse($projectItems as $item)<article class="nt-project-card"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><span>{{ data_get($item, 'category', data_get($item, 'meta', 'Du an noi that')) }}</span><h3>{{ data_get($item, 'title', 'Cong trinh noi that hien dai') }}</h3></a></article>@empty <p>Dang cap nhat du an.</p>@endforelse</div></div></section>

    <section id="dich-vu" class="nt-section nt-services"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($services, 'data.subtitle', 'Dich vu cua chung toi') }}</p><h2>{{ data_get($services, 'data.title', 'Dong hanh tu y tuong den hoan thien') }}</h2></div><div class="nt-service-grid">@forelse($serviceItems as $item)<article class="nt-service-card"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=800&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><h3>{{ data_get($item, 'title', 'Dich vu noi that') }}</h3><p>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 145) }}</p><a href="{{ data_get($item, 'url', '#') }}">Tim hieu them</a></div></article>@empty <p>Dang cap nhat dich vu.</p>@endforelse</div></div></section>

    @php($featuredTestimonial = $testimonialItems[0] ?? [])
    <section class="nt-testimonial"><div class="nt-testimonial__copy"><div><p class="nt-eyebrow">{{ data_get($testimonials, 'data.subtitle', 'Cam nhan khach hang') }}</p><h2>{{ data_get($testimonials, 'data.title', 'Dieu khach hang noi ve chung toi') }}</h2><blockquote>“{{ data_get($featuredTestimonial, 'quote', data_get($featuredTestimonial, 'summary', 'Doi ngu tan tam, thiet ke chi tiet va quy trinh thi cong rat chuyen nghiep.')) }}”</blockquote><strong>{{ data_get($featuredTestimonial, 'name', data_get($featuredTestimonial, 'title', 'Khach hang NT501')) }}</strong><small>{{ data_get($featuredTestimonial, 'role', 'Chu nha') }}</small></div></div><div class="nt-testimonial__media"><img src="{{ data_get($featuredTestimonial, 'image', 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1400&q=85') }}" alt="Khach hang NT501"></div></section>

    <section id="tin-tuc" class="nt-section nt-blog"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($posts, 'data.subtitle', 'Tin tuc va cap nhat') }}</p><h2>{{ data_get($posts, 'data.title', 'Bai viet gan day') }}</h2></div><div class="nt-blog__grid">@forelse($postItems as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600210491369-e753d80a41f3?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><h3>{{ data_get($item, 'title', 'Cam hung thiet ke noi that') }}</h3><small>{{ data_get($item, 'published_at', data_get($item, 'meta', 'NT501 Interior Studio')) }}</small><p>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 115) }}</p></div></a></article>@empty <p>Dang cap nhat bai viet.</p>@endforelse</div></div></section>

    <section class="nt-partners nt-section"><div class="nt-container"><p class="nt-eyebrow">Doi tac cua chung toi</p><div>@forelse($partnerItems as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'logo', 'https://placehold.co/220x100/f5f1e8/38342c?text=Partner')) }}" alt="{{ data_get($item, 'title', 'Partner') }}"></a>@empty <span>NT501</span><span>ARCHITECT</span><span>INTERIOR</span><span>BUILD</span>@endforelse</div></div></section>
    <section class="nt-section nt-stats"><div class="nt-container"><div>@forelse($statItems as $item)<article><strong>{{ data_get($item, 'value', data_get($item, 'title', '10+')) }}</strong><span>{{ data_get($item, 'summary', data_get($item, 'description', 'Nam kinh nghiem')) }}</span></article>@empty <article><strong>10+</strong><span>Nam lam viec</span></article><article><strong>20</strong><span>Chuyen gia noi that</span></article><article><strong>1000</strong><span>Du an tiem nang</span></article>@endforelse</div></div></section>
</main>
@endsection
