@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = collect(\App\Support\FrontendLocalization::supportedLocales())->map(fn ($locale) => ['code' => $locale, 'label' => strtoupper($locale)])->all();
    $block = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $item): array {
        $dynamic = collect($item['dynamic_items'] ?? [])->filter()->values();
        if ($dynamic->isNotEmpty()) {
            return $dynamic->all();
        }

        return collect(data_get($item, 'data.content.items', []))->filter()->values()->all();
    };
    $image = fn (array $item, string $fallback): string => (string) data_get($item, 'image', data_get($item, 'image_url', data_get($item, 'avatar', $fallback)));
    $summary = fn ($text, int $limit = 150): string => \Illuminate\Support\Str::limit(trim(strip_tags((string) $text)), $limit);
    $hero = $block('hero_slider');
    $about = $block('about_experience');
    $stats = $block('stats_strip');
    $categories = $block('featured_categories');
    $products = $block('business_service_grid');
    $services = $block('featured_services');
    $process = $block('process_steps');
    $faq = $block('faq_showcase');
    $testimonials = $block('testimonials');
    $team = $block('team_members');
    $news = $block('latest_posts');
    $slides = collect(data_get($hero, 'dynamic_items', []))->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }
    if ($slides->isEmpty()) {
        $slides = collect([['title' => 'Thực phẩm hữu cơ tươi chất lượng cao', 'summary' => 'Sản phẩm nông nghiệp tự nhiên', 'button_label' => 'Xem ngay', 'image' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#san-pham']]);
    }
    $benefits = collect(data_get($hero, 'data.content.items', []))->filter()->values();
@endphp
@extends('theme-xd0323::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'XD0323 Euro Farm')))
@section('content')
<main class="xd323-main">
    <section class="xd323-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider">
        @foreach ($slides as $index => $slide)
            <article class="xd323-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-c323-slide>
                <img src="{{ $image($slide, 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=2200&q=90') }}" alt="{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}">
                <div class="xd323-hero__overlay"></div>
                <div class="xd323-container xd323-hero__content">
                    <p>{{ data_get($slide, 'summary', data_get($hero, 'data.subtitle')) }}</p>
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                    <a class="xd323-btn" href="{{ data_get($slide, 'link_url', '#san-pham') }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Xem ngay')) }}</a>
                </div>
            </article>
        @endforeach
        <button class="xd323-hero__arrow xd323-hero__arrow--prev" type="button" data-c323-prev aria-label="Slide trước"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="xd323-hero__arrow xd323-hero__arrow--next" type="button" data-c323-next aria-label="Slide sau"><i class="fa-solid fa-chevron-right"></i></button>
    </section>

    <section class="xd323-benefits">
        <div class="xd323-container xd323-benefits__track">
            @foreach ($benefits as $item)
                <article>
                    <span><i class="{{ data_get($item, 'icon', 'fa-solid fa-truck-fast') }}"></i></span>
                    <div>
                        <h3>{{ data_get($item, 'title') }}</h3>
                        <p>{{ data_get($item, 'summary') }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section id="gioi-thieu" class="xd323-section xd323-about xd-landing-block" data-landing-block-id="{{ data_get($about, 'id') }}" data-block-type="about_experience">
        <div class="xd323-container xd323-about__grid">
            <div class="xd323-about__media">
                <img class="xd323-about__leaf" src="{{ data_get($about, 'media.image', 'https://images.unsplash.com/photo-1492496913980-501348b61469?auto=format&fit=crop&w=1000&q=85') }}" alt="{{ data_get($about, 'data.title') }}">
                <img class="xd323-about__round" src="{{ data_get($about, 'media.secondary_image', 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($about, 'data.title') }}">
            </div>
            <div class="xd323-about__body">
                <p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($about, 'data.subtitle', 'Về Euro Farm') }}</p>
                <h2>{{ data_get($about, 'data.title') }}</h2>
                <p>{{ data_get($about, 'data.description') }}</p>
                <div class="xd323-about__features">
                    @foreach ($items($about) as $item)
                        <article>
                            <span><i class="{{ data_get($item, 'icon', 'fa-solid fa-seedling') }}"></i></span>
                            <strong>{{ data_get($item, 'title') }}</strong>
                        </article>
                    @endforeach
                </div>
                <div class="xd323-about__cta">
                    <a class="xd323-btn" href="{{ data_get($about, 'settings.cta_url', '#san-pham') }}">{{ data_get($about, 'data.button_label', 'Xem thêm') }} <i class="fa-solid fa-arrow-right"></i></a>
                    <div><i class="fa-solid fa-phone-volume"></i><span>Liên hệ ngay cho chúng tôi</span><b>{{ data_get($about, 'data.content.phone', '1900 6750') }}</b></div>
                </div>
            </div>
        </div>
    </section>

    <section id="thong-ke" class="xd323-section xd323-stats xd-landing-block" data-landing-block-id="{{ data_get($stats, 'id') }}" data-block-type="stats_strip">
        <div class="xd323-container xd323-stats__grid">
            <div>
                <p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($stats, 'data.subtitle') }}</p>
                <h2>{{ data_get($stats, 'data.title') }}</h2>
                <p>{{ data_get($stats, 'data.description') }}</p>
            </div>
            <div class="xd323-stats__cards">
                @foreach ($items($stats) as $item)
                    <article>
                        <i class="{{ data_get($item, 'icon', 'fa-solid fa-box-open') }}"></i>
                        <div><strong>{{ data_get($item, 'title') }}</strong><span>{{ data_get($item, 'summary') }}</span></div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="danh-muc" class="xd323-section xd323-categories xd-landing-block" data-landing-block-id="{{ data_get($categories, 'id') }}" data-block-type="featured_categories">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($categories, 'data.subtitle') }}</p><h2>{{ data_get($categories, 'data.title') }}</h2></header>
            <div class="xd323-circle-track">
                @foreach ($items($categories) as $item)
                    <a href="{{ data_get($item, 'url', '#') }}">
                        <span><img src="{{ $image($item, 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=500&q=85') }}" alt="{{ data_get($item, 'title') }}"></span>
                        <strong>{{ data_get($item, 'title') }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section id="san-pham" class="xd323-section xd323-products xd-landing-block" data-landing-block-id="{{ data_get($products, 'id') }}" data-block-type="business_service_grid">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($products, 'data.subtitle') }}</p><h2>{{ data_get($products, 'data.title') }}</h2></header>
            <div class="xd323-product-grid">
                @foreach ($items($products) as $item)
                    <article>
                        <button type="button" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i></button>
                        <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c?auto=format&fit=crop&w=700&q=85') }}" alt="{{ data_get($item, 'title') }}"></a>
                        <h3>{{ data_get($item, 'title') }}</h3>
                        @if (filled(data_get($item, 'price_formatted', data_get($item, 'price'))))
                            <strong>{{ data_get($item, 'price_formatted', data_get($item, 'price')) }}</strong>
                        @elseif (filled(data_get($item, 'summary')))
                            <p>{{ $summary(data_get($item, 'summary'), 70) }}</p>
                        @endif
                        <a class="xd323-cart-fab" href="{{ data_get($item, 'url', '#') }}" aria-label="Xem sản phẩm"><i class="fa-solid fa-cart-shopping"></i></a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="dich-vu" class="xd323-section xd323-services xd-landing-block" data-landing-block-id="{{ data_get($services, 'id') }}" data-block-type="featured_services">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($services, 'data.subtitle') }}</p><h2>{{ data_get($services, 'data.title') }}</h2></header>
        </div>
        <div class="xd323-wide-track">
            @foreach ($items($services) as $item)
                <article>
                    <img src="{{ $image($item, 'https://images.unsplash.com/photo-1523741543316-beb7fc7023d8?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title') }}">
                    <a href="{{ data_get($item, 'url', '#') }}"><i class="{{ data_get($item, 'icon', 'fa-solid fa-seedling') }}"></i><strong>{{ data_get($item, 'title') }}</strong><span><i class="fa-solid fa-arrow-right"></i></span></a>
                </article>
            @endforeach
        </div>
    </section>

    <section id="quy-trinh" class="xd323-section xd323-process xd-landing-block" data-landing-block-id="{{ data_get($process, 'id') }}" data-block-type="process_steps">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($process, 'data.subtitle') }}</p><h2>{{ data_get($process, 'data.title') }}</h2></header>
            <div class="xd323-process__steps">
                @foreach ($items($process) as $index => $item)
                    <article>
                        <img src="{{ $image($item, 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=700&q=85') }}" alt="{{ data_get($item, 'title') }}">
                        <span>{{ $index + 1 }}</span>
                        <h3>{{ data_get($item, 'title') }}</h3>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="hoi-dap" class="xd323-section xd323-faq xd-landing-block" data-landing-block-id="{{ data_get($faq, 'id') }}" data-block-type="faq_showcase">
        <div class="xd323-container xd323-faq__grid">
            <img src="{{ data_get($faq, 'media.image', 'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($faq, 'data.title') }}">
            <div>
                <p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($faq, 'data.subtitle') }}</p>
                <h2>{{ data_get($faq, 'data.title') }}</h2>
                <p>{{ data_get($faq, 'data.description') }}</p>
                <div class="xd323-faq__list">
                    @foreach ($items($faq) as $index => $item)
                        <details {{ $index === 0 ? 'open' : '' }}>
                            <summary>{{ data_get($item, 'title') }} <i class="fa-solid fa-chevron-down"></i></summary>
                            <p>{{ data_get($item, 'summary') }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="danh-gia" class="xd323-section xd323-testimonials xd-landing-block" data-landing-block-id="{{ data_get($testimonials, 'id') }}" data-block-type="testimonials">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($testimonials, 'data.subtitle') }}</p><h2>{{ data_get($testimonials, 'data.title') }}</h2></header>
            <div class="xd323-testimonial-grid">
                @foreach ($items($testimonials) as $item)
                    <article>
                        <img src="{{ $image($item, 'https://images.unsplash.com/photo-1529626455594-4ff0802cfb7e?auto=format&fit=crop&w=500&q=85') }}" alt="{{ data_get($item, 'name', data_get($item, 'title')) }}">
                        <div>
                            <span>★★★★★</span>
                            <p>"{{ data_get($item, 'quote', data_get($item, 'summary')) }}"</p>
                            <h3>{{ data_get($item, 'name', data_get($item, 'title')) }}</h3>
                            <small>{{ data_get($item, 'company', data_get($item, 'role')) }}</small>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="doi-ngu" class="xd323-section xd323-team xd-landing-block" data-landing-block-id="{{ data_get($team, 'id') }}" data-block-type="team_members">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($team, 'data.subtitle') }}</p><h2>{{ data_get($team, 'data.title') }}</h2></header>
            <div class="xd323-team-grid">
                @foreach ($items($team) as $item)
                    <article>
                        <img src="{{ $image($item, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=600&q=85') }}" alt="{{ data_get($item, 'name', data_get($item, 'title')) }}">
                        <a href="#doi-ngu" aria-label="Chia sẻ"><i class="fa-solid fa-share-nodes"></i></a>
                        <h3>{{ data_get($item, 'name', data_get($item, 'title')) }}</h3>
                        <p>{{ data_get($item, 'role', data_get($item, 'summary')) }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="tin-tuc" class="xd323-section xd323-news xd-landing-block" data-landing-block-id="{{ data_get($news, 'id') }}" data-block-type="latest_posts">
        <div class="xd323-container">
            <header class="xd323-heading"><p class="xd323-kicker"><i class="fa-brands fa-pagelines"></i>{{ data_get($news, 'data.subtitle') }}</p><h2>{{ data_get($news, 'data.title') }}</h2></header>
            <div class="xd323-news-grid">
                @foreach ($items($news) as $item)
                    <article>
                        <a href="{{ data_get($item, 'url', '#') }}">
                            <div><img src="{{ $image($item, 'https://images.unsplash.com/photo-1490818387583-1baba5e638af?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title') }}"><time>{{ data_get($item, 'published_at', now()->format('d/m/Y')) }}</time></div>
                            <p><i class="fa-regular fa-user"></i>{{ data_get($item, 'author', 'Trần Tấn Phát') }} <i class="fa-regular fa-comment-dots"></i>0 bình luận</p>
                            <h3>{{ data_get($item, 'title') }}</h3>
                            <span class="xd323-btn">Đọc tiếp <i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
    <span id="lien-he"></span>
</main>
@endsection
