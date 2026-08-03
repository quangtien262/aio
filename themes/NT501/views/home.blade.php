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
    if ($slides->isEmpty()) $slides = collect([['title' => 'Không gian đẹp, chất lượng bền vững', 'summary' => 'Thiết kế và thi công nội thất cho những ngôi nhà mang dấu ấn riêng.', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2200&q=90', 'button_label' => 'Khám phá dự án', 'link_url' => '#du-an']]);
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
                <div class="nt-container nt-hero__content"><p>{{ data_get($hero, 'data.subtitle', 'Thiết kế và thi công nội thất') }}</p><h1>{{ data_get($slide, 'title') }}</h1><div>{{ data_get($slide, 'summary', data_get($hero, 'data.description', '')) }}</div><a class="nt-button" href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#du-an')) }}">{{ data_get($slide, 'button_label', 'Khám phá thêm') }}</a></div>
            </article>
        @endforeach
    </section>

    <section id="gioi-thieu" class="nt-about nt-section"><div class="nt-container nt-about__grid"><div class="nt-about__copy"><p class="nt-eyebrow">{{ data_get($about, 'data.subtitle', 'Giới thiệu về NT501') }}</p><h2>{{ data_get($about, 'data.title', 'Kiến tạo không gian sống đầy cảm hứng') }}</h2><div>{!! nl2br(e(data_get($about, 'data.description', 'Chúng tôi kết hợp tư duy thiết kế tinh tế, vật liệu chất lượng và quy trình thi công kỹ lưỡng để biến mỗi ý tưởng thành một không gian đáng sống.'))) !!}</div><a class="nt-text-link" href="{{ data_get($about, 'settings.cta_url', '#dich-vu') }}">{{ data_get($about, 'data.button_label', 'Xem dịch vụ') }}</a></div><div class="nt-about__media"><img src="{{ $aboutImage }}" alt="{{ data_get($about, 'data.title', 'NT501') }}"><span>10+<small>năm đồng hành</small></span></div></div></section>

    <section class="nt-section nt-showcase"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($showcase, 'data.subtitle', 'Không gian tiêu biểu') }}</p><h2>{{ data_get($showcase, 'data.title', 'Giải pháp cho mọi phong cách sống') }}</h2></div><div class="nt-showcase__grid">@forelse ($showcaseItems as $item)<article class="nt-showcase__card"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><a href="{{ data_get($item, 'url', '#') }}"><h3>{{ data_get($item, 'title', 'Thiết kế nội thất') }}</h3><span>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 95) }}</span></a></article>@empty <p>Đang cập nhật dự án.</p>@endforelse <aside class="nt-hours"><p>Tư vấn cùng NT501</p><h3>Đặt lịch trao đổi</h3><strong>Thứ hai - Thứ bảy<br>08:30 - 18:00</strong><a class="nt-button" href="#lien-he">Nhận báo giá</a></aside></div></div></section>

    <section id="du-an" class="nt-section nt-projects"><div class="nt-container"><div class="nt-section-head nt-section-head--row"><div><p class="nt-eyebrow">{{ data_get($projects, 'data.subtitle', 'Dự án đã thực hiện') }}</p><h2>{{ data_get($projects, 'data.title', 'Những công trình nổi bật') }}</h2></div><a class="nt-text-link" href="{{ data_get($projects, 'settings.cta_url', '#du-an') }}">Xem tất cả</a></div><div class="nt-rail">@forelse($projectItems as $item)<article class="nt-project-card"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><span>{{ data_get($item, 'category', data_get($item, 'meta', 'Dự án nội thất')) }}</span><h3>{{ data_get($item, 'title', 'Công trình nội thất hiện đại') }}</h3></a></article>@empty <p>Đang cập nhật dự án.</p>@endforelse</div></div></section>

    <section id="dich-vu" class="nt-section nt-services"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($services, 'data.subtitle', 'Dịch vụ của chúng tôi') }}</p><h2>{{ data_get($services, 'data.title', 'Đồng hành từ ý tưởng đến hoàn thiện') }}</h2></div><div class="nt-service-grid">@forelse($serviceItems as $item)<article class="nt-service-card"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=800&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><h3>{{ data_get($item, 'title', 'Dịch vụ nội thất') }}</h3><p>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 145) }}</p><a href="{{ data_get($item, 'url', '#') }}">Tìm hiểu thêm</a></div></article>@empty <p>Đang cập nhật dịch vụ.</p>@endforelse</div></div></section>

    @php($featuredTestimonial = $testimonialItems[0] ?? [])
    <section class="nt-testimonial"><div class="nt-testimonial__copy"><div><p class="nt-eyebrow">{{ data_get($testimonials, 'data.subtitle', 'Cảm nhận khách hàng') }}</p><h2>{{ data_get($testimonials, 'data.title', 'Điều khách hàng nói về chúng tôi') }}</h2><blockquote>“{{ data_get($featuredTestimonial, 'quote', data_get($featuredTestimonial, 'summary', 'Đội ngũ tận tâm, thiết kế chi tiết và quy trình thi công rất chuyên nghiệp.')) }}”</blockquote><strong>{{ data_get($featuredTestimonial, 'name', data_get($featuredTestimonial, 'title', 'Khách hàng NT501')) }}</strong><small>{{ data_get($featuredTestimonial, 'role', 'Chủ nhà') }}</small></div></div><div class="nt-testimonial__media"><img src="{{ data_get($featuredTestimonial, 'image', 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1400&q=85') }}" alt="Khách hàng NT501"></div></section>

    <section id="tin-tuc" class="nt-section nt-blog"><div class="nt-container"><div class="nt-section-head"><p class="nt-eyebrow">{{ data_get($posts, 'data.subtitle', 'Tin tức và cập nhật') }}</p><h2>{{ data_get($posts, 'data.title', 'Bài viết gần đây') }}</h2></div><div class="nt-blog__grid">@forelse($postItems as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', 'https://images.unsplash.com/photo-1600210491369-e753d80a41f3?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title', '') }}"><div><h3>{{ data_get($item, 'title', 'Cảm hứng thiết kế nội thất') }}</h3><small>{{ data_get($item, 'published_at', data_get($item, 'meta', 'NT501 Interior Studio')) }}</small><p>{{ $limit(data_get($item, 'summary', data_get($item, 'description', '')), 115) }}</p></div></a></article>@empty <p>Đang cập nhật bài viết.</p>@endforelse</div></div></section>

    <section class="nt-partners nt-section"><div class="nt-container"><p class="nt-eyebrow">Đối tác của chúng tôi</p><div>@forelse($partnerItems as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'logo', 'https://placehold.co/220x100/f5f1e8/38342c?text=Partner')) }}" alt="{{ data_get($item, 'title', 'Partner') }}"></a>@empty <span>NT501</span><span>ARCHITECT</span><span>INTERIOR</span><span>BUILD</span>@endforelse</div></div></section>
    <section class="nt-section nt-stats"><div class="nt-container"><div>@forelse($statItems as $item)<article><strong>{{ data_get($item, 'value', data_get($item, 'title', '10+')) }}</strong><span>{{ data_get($item, 'summary', data_get($item, 'description', 'Năm kinh nghiệm')) }}</span></article>@empty <article><strong>10+</strong><span>Năm làm việc</span></article><article><strong>20</strong><span>Chuyên gia nội thất</span></article><article><strong>1000</strong><span>Dự án tiềm năng</span></article>@endforelse</div></div></section>
</main>
@endsection
