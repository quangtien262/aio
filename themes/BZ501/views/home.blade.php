@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = collect(data_get($landingEditorOptions ?? [], 'locales', []))->all();
    $block = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block): array {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty()
            ? $dynamic->all()
            : collect(data_get($block, 'data.content.items', []))->filter()->values()->all();
    };
    $fallbackImage = 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1200&q=85';
    $hero = $block('hero_slider');
    $quality = $block('featured_categories');
    $about = $block('about_experience');
    $services = $block('featured_services');
    $mission = $block('content_showcase');
    $cases = $block('content_mosaic');
    $reason = $block('logistics_feature_panel');
    $team = $block('team_members');
    $products = $block('featured_products');
    $partners = $block('partner_logos');
    $posts = $block('latest_posts');
    $slides = collect(data_get($hero, 'data.content.slides', []))->whenEmpty(fn () => collect($hero['dynamic_items'] ?? []))->values();
    if ($slides->isEmpty()) {
        $slides = collect([[
            'kicker' => data_get($hero, 'data.subtitle', 'Thiết kế & thi công'),
            'title' => data_get($hero, 'data.title', 'Xây nhà trọn gói'),
            'summary' => data_get($hero, 'data.description', 'Cung cấp dịch vụ thi công trọn gói với quy trình linh hoạt và chuyên nghiệp.'),
            'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=2200&q=90',
            'button_label' => data_get($hero, 'data.button_label', 'Liên hệ báo giá'),
            'link_url' => '#footer',
        ]]);
    }
@endphp

@extends('theme-bz501::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'BZ501 HaluFin')))

@section('content')
<main class="bz501-main">
    <section class="bz501-hero">
        @foreach($slides as $index => $slide)
            <article class="bz501-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-bz501-hero-slide>
                <img src="{{ data_get($slide, 'image', $fallbackImage) }}" alt="{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}">
                <div class="bz501-hero__shade"></div>
                <div class="bz501-container bz501-hero__copy">
                    <span>{{ data_get($slide, 'kicker', data_get($slide, 'subtitle', data_get($hero, 'data.subtitle'))) }}</span>
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                    <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                    <a href="{{ data_get($slide, 'url', data_get($slide, 'link_url', '#footer')) }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Liên hệ báo giá')) }}</a>
                </div>
            </article>
        @endforeach
    </section>

    <section id="chat-luong" class="bz501-quality">
        <div class="bz501-container bz501-quality__rail">
            @foreach($items($quality) as $item)
                <article>
                    <span class="bz501-quality__icon" style="--accent: {{ data_get($item, 'color', '#ff3517') }}"><i class="{{ data_get($item, 'icon', 'fa-solid fa-chart-line') }}"></i></span>
                    <h3>{{ data_get($item, 'title') }}</h3>
                    <p>{{ data_get($item, 'summary', data_get($item, 'description')) }}</p>
                    <a href="{{ data_get($item, 'url', '#dich-vu') }}">Xem thêm <i class="fa-regular fa-hand-point-right"></i></a>
                </article>
            @endforeach
        </div>
    </section>

    <section id="gioi-thieu" class="bz501-section bz501-about">
        <div class="bz501-container bz501-about__grid">
            <div class="bz501-about__media">
                <img src="{{ data_get($about, 'media.image', 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=85') }}" alt="{{ data_get($about, 'data.title') }}">
                <div class="bz501-about__stat">
                    <span>{{ data_get($about, 'data.content.stat_label', 'Lượng người theo dõi') }}</span>
                    <i></i><i></i>
                </div>
            </div>
            <div class="bz501-copy">
                <span class="bz501-kicker">{{ data_get($about, 'data.subtitle', 'Giới thiệu') }}</span>
                <h2>{{ data_get($about, 'data.title') }}</h2>
                <p>{{ data_get($about, 'data.description') }}</p>
                <div class="bz501-about__points">
                    @foreach(data_get($about, 'data.content.items', []) as $item)
                        <strong><i class="fa-solid fa-circle-check"></i>{{ data_get($item, 'title') }}</strong>
                    @endforeach
                </div>
                <div class="bz501-signature">
                    <img src="{{ data_get($about, 'media.avatar', 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=300&q=80') }}" alt="{{ data_get($about, 'data.content.author', 'Founder') }}">
                    <p><strong>{{ data_get($about, 'data.content.author', 'Mr. Robert Smith') }}</strong><br>{{ data_get($about, 'data.content.role', 'CEO & Founder') }}</p>
                    <em>{{ data_get($about, 'data.content.signature', 'Signature') }}</em>
                </div>
            </div>
        </div>
    </section>

    <section id="dich-vu" class="bz501-section bz501-card-grid-section">
        <div class="bz501-container">
            <header class="bz501-heading">
                <span class="bz501-kicker">{{ data_get($services, 'data.subtitle', 'Dịch vụ') }}</span>
                <h2>{{ data_get($services, 'data.title') }}</h2>
            </header>
            <div class="bz501-service-grid">
                @foreach($items($services) as $item)
                    <article>
                        <img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'alt', data_get($item, 'title')) }}">
                        <span><i class="{{ data_get($item, 'icon', 'fa-solid fa-chart-pie') }}"></i></span>
                        <h3>{{ data_get($item, 'title') }}</h3>
                        <p>{{ data_get($item, 'summary') }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bz501-section bz501-mission">
        <div class="bz501-container bz501-mission__grid">
            <div class="bz501-mission__images">
                <img src="{{ data_get($mission, 'media.image', 'https://images.unsplash.com/photo-1556761175-4b46a572b786?auto=format&fit=crop&w=1200&q=85') }}" alt="{{ data_get($mission, 'data.title') }}">
                <img src="{{ data_get($mission, 'media.secondary_image', 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=600&q=85') }}" alt="{{ data_get($mission, 'data.subtitle') }}">
            </div>
            <div class="bz501-copy">
                <span class="bz501-kicker">{{ data_get($mission, 'data.subtitle', 'Mục tiêu') }}</span>
                <h2>{{ data_get($mission, 'data.title') }}</h2>
                <p>{{ data_get($mission, 'data.description') }}</p>
                @foreach(data_get($mission, 'data.content.items', []) as $item)
                    <div class="bz501-mission__point">
                        <i class="{{ data_get($item, 'icon', 'fa-solid fa-arrow-trend-up') }}"></i>
                        <div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bz501-section bz501-showcase">
        <div class="bz501-container">
            <header class="bz501-heading">
                <span class="bz501-kicker">{{ data_get($cases, 'data.subtitle', 'Nghiên cứu') }}</span>
                <h2>{{ data_get($cases, 'data.title') }}</h2>
            </header>
        </div>
        <div class="bz501-wide-rail">
            @foreach($items($cases) as $item)
                <a href="{{ data_get($item, 'url', '#') }}">
                    <img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'title') }}">
                    <span>{{ data_get($item, 'summary', data_get($item, 'tag')) }}</span>
                    <strong>{{ data_get($item, 'title') }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="bz501-reason" style="--reason-bg: url('{{ data_get($reason, 'media.background', 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=2200&q=85') }}')">
        <div class="bz501-container bz501-reason__grid">
            <img src="{{ data_get($reason, 'media.image', 'https://images.unsplash.com/photo-1551836022-4c4c79ecde51?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($reason, 'data.title') }}">
            <div>
                <span class="bz501-kicker bz501-kicker--dark">{{ data_get($reason, 'data.subtitle', 'Lý do chọn chúng tôi') }}</span>
                <h2>{{ data_get($reason, 'data.title') }}</h2>
                <p>{{ data_get($reason, 'data.description') }}</p>
                <ul>
                    @foreach(data_get($reason, 'data.content.items', []) as $item)
                        <li><i class="fa-solid fa-circle-check"></i>{{ data_get($item, 'title') }}</li>
                    @endforeach
                </ul>
                <div class="bz501-hotline"><i class="fa-solid fa-play"></i><span>{{ data_get($reason, 'data.content.support_label', '12/7 Support Team') }}</span><strong>{{ data_get($reason, 'data.content.phone', '+123-55-05800') }}</strong></div>
            </div>
        </div>
    </section>

    <section id="doi-ngu" class="bz501-section bz501-team-section">
        <div class="bz501-container">
            <header class="bz501-heading">
                <span class="bz501-kicker">{{ data_get($team, 'data.subtitle', 'Đội ngũ nhân viên') }}</span>
                <h2>{{ data_get($team, 'data.title') }}</h2>
            </header>
            <div class="bz501-team">
                @foreach($items($team) as $member)
                    <article>
                        <img src="{{ data_get($member, 'image', $fallbackImage) }}" alt="{{ data_get($member, 'name', data_get($member, 'title')) }}">
                        <h3>{{ data_get($member, 'name', data_get($member, 'title')) }}</h3>
                        <p>{{ data_get($member, 'role', data_get($member, 'summary')) }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="bz501-section bz501-product-section">
        <div class="bz501-container">
            <header class="bz501-heading">
                <span class="bz501-kicker">{{ data_get($products, 'data.subtitle', 'Sản phẩm') }}</span>
                <h2>{{ data_get($products, 'data.title') }}</h2>
            </header>
            <div class="bz501-product-rail">
                @foreach($items($products) as $item)
                    <a href="{{ data_get($item, 'url', '#') }}">
                        <img src="{{ data_get($item, 'image', $fallbackImage) }}" alt="{{ data_get($item, 'title') }}">
                        <strong>{{ data_get($item, 'title') }}</strong>
                        <span>{{ data_get($item, 'price_label', data_get($item, 'summary')) }}</span>
                        <em>★★★★★</em>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bz501-partners">
        <div class="bz501-container bz501-partners__rail">
            @foreach($items($partners) as $partner)
                <a href="{{ data_get($partner, 'url', data_get($partner, 'href', '#')) }}">
                    @if(data_get($partner, 'image'))
                        <img src="{{ data_get($partner, 'image') }}" alt="{{ data_get($partner, 'name', data_get($partner, 'title')) }}">
                    @else
                        <strong>{{ data_get($partner, 'name', data_get($partner, 'title', 'Partner')) }}</strong>
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <section id="tin-tuc" class="bz501-section bz501-news-section">
        <div class="bz501-container">
            <header class="bz501-heading">
                <span class="bz501-kicker">{{ data_get($posts, 'data.subtitle', 'Tin tức') }}</span>
                <h2>{{ data_get($posts, 'data.title') }}</h2>
            </header>
            <div class="bz501-news-grid">
                @foreach($items($posts) as $post)
                    <article>
                        <img src="{{ data_get($post, 'image', $fallbackImage) }}" alt="{{ data_get($post, 'title') }}">
                        <time><i class="fa-regular fa-calendar-days"></i>{{ data_get($post, 'date', now()->format('d/m/Y')) }}</time>
                        <h3>{{ data_get($post, 'title') }}</h3>
                        <p>{{ data_get($post, 'summary') }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</main>
@endsection
