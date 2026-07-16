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
    $contentImage = fn (array $item, string $fallback): string => (string) data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $short = fn ($text, int $limit = 120): string => \Illuminate\Support\Str::limit(trim(strip_tags((string) $text)), $limit);
    $hero = $block('hero_slider');
    $about = $block('about_experience');
    $values = $block('featured_categories');
    $signature = $block('content_mosaic');
    $services = $block('featured_services');
    $projects = $block('project_gallery');
    $process = $block('process_steps');
    $news = $block('latest_posts');
    $slides = collect(data_get($hero, 'dynamic_items', []))->filter()->values();
    if ($slides->isEmpty()) {
        $slides = collect(data_get($hero, 'data.content.slides', []))->filter()->values();
    }
    if ($slides->isEmpty()) {
        $slides = collect([['title' => 'Thiết kế độc đáo', 'summary' => 'Mỗi dự án là một câu chuyện, mỗi thiết kế là một tác phẩm nghệ thuật độc lập.', 'subtitle' => 'Sáng tạo không giới hạn', 'image' => 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=2200&q=90']]);
    }
    $aboutImages = collect(data_get($about, 'media.images', []))->filter()->values();
    $signatureImages = collect(data_get($signature, 'media.images', []))->filter()->values();
@endphp
@extends('theme-xd0324::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'XD0324 WolfArch')))
@section('content')
<main class="xd324-main">
    <section class="xd324-hero" data-xd-block="{{ data_get($hero, 'id') }}">
        @foreach ($slides as $index => $slide)
            <article class="xd324-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-c324-slide>
                <img src="{{ $contentImage($slide, 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=2200&q=90') }}" alt="{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}">
                <div class="xd324-hero__shade"></div>
                <div class="xd324-container xd324-hero__content">
                    <p>{{ data_get($slide, 'subtitle', data_get($hero, 'data.subtitle', 'Sáng tạo không giới hạn')) }}</p>
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title', 'Thiết kế độc đáo')) }}</h1>
                    <span>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</span>
                </div>
            </article>
        @endforeach
        <button type="button" class="xd324-hero__nav xd324-hero__nav--prev" data-c324-prev aria-label="Slide trước"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="xd324-hero__nav xd324-hero__nav--next" data-c324-next aria-label="Slide sau"><i class="fa-solid fa-chevron-right"></i></button>
        <a class="xd324-float xd324-float--bell" href="#lien-he" aria-label="Thông báo"><i class="fa-regular fa-bell"></i></a>
        <a class="xd324-float xd324-float--phone" href="#lien-he" aria-label="Gọi tư vấn"><i class="fa-solid fa-phone"></i></a>
    </section>

    <section id="gioi-thieu" class="xd324-section xd324-about reveal" data-xd-block="{{ data_get($about, 'id') }}">
        <div class="xd324-container xd324-about__grid">
            <div class="xd324-about__mosaic">
                @foreach (collect([0,1,2,3]) as $index)
                    <img src="{{ $aboutImages[$index] ?? ['https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1600566752355-35792bedcfea?auto=format&fit=crop&w=900&q=85'][$index] }}" alt="{{ data_get($about, 'data.title') }}">
                @endforeach
            </div>
            <div class="xd324-about__body">
                <p class="xd324-eyebrow">{{ data_get($about, 'data.subtitle', 'Về chúng tôi') }}</p>
                <h2>{!! data_get($about, 'data.title', 'Tạo ra những <em>không gian đẹp</em>') !!}</h2>
                <p>{{ data_get($about, 'data.description') }}</p>
                <div class="xd324-about__stats">
                    @foreach ($items($about) as $item)
                        <article><strong>{{ data_get($item, 'title') }}</strong><span>{{ data_get($item, 'summary') }}</span></article>
                    @endforeach
                </div>
                <a class="xd324-btn" href="{{ data_get($about, 'settings.cta_url', '#du-an') }}">{{ data_get($about, 'data.button_label', 'Tìm hiểu thêm') }} <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <section class="xd324-section xd324-values reveal" data-xd-block="{{ data_get($values, 'id') }}">
        <div class="xd324-container xd324-values__grid">
            <div>
                <p class="xd324-eyebrow">{{ data_get($values, 'data.subtitle', 'Tại sao chọn chúng tôi') }}</p>
                <h2>{!! data_get($values, 'data.title', 'Khám phá <em>giá trị chúng tôi mang lại</em> trong mỗi dự án') !!}</h2>
                <p class="xd324-lead">{{ data_get($values, 'data.description') }}</p>
                <div class="xd324-value-list">
                    @foreach ($items($values) as $item)
                        <article>
                            <i class="{{ data_get($item, 'icon', 'fa-regular fa-lightbulb') }}"></i>
                            <div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="xd324-values__mosaic">
                @foreach (collect([0,1,2,3]) as $index)
                    <img src="{{ $signatureImages[$index] ?? ['https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=85'][$index] }}" alt="{{ data_get($values, 'data.title') }}">
                @endforeach
            </div>
        </div>
    </section>

    <section class="xd324-section xd324-signature reveal" data-xd-block="{{ data_get($signature, 'id') }}">
        <div class="xd324-container xd324-values__grid">
            <div>
                <p class="xd324-eyebrow">{{ data_get($signature, 'data.subtitle', 'Tại sao chọn chúng tôi') }}</p>
                <h2>{!! data_get($signature, 'data.title', 'Khám phá <em>giá trị chúng tôi mang lại</em> trong mỗi dự án') !!}</h2>
                <p class="xd324-lead">{{ data_get($signature, 'data.description') }}</p>
                <div class="xd324-value-list">
                    @foreach ($items($signature) as $item)
                        <article>
                            <i class="{{ data_get($item, 'icon', 'fa-regular fa-gem') }}"></i>
                            <div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="xd324-values__mosaic xd324-values__mosaic--alt">
                @foreach (collect([0,1,2,3]) as $index)
                    <img src="{{ $signatureImages[$index] ?? ['https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=900&q=85','https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=900&q=85'][$index] }}" alt="{{ data_get($signature, 'data.title') }}">
                @endforeach
            </div>
        </div>
    </section>

    <section id="dich-vu" class="xd324-section xd324-slider-block reveal" data-xd-block="{{ data_get($services, 'id') }}">
        <div class="xd324-container">
            <div class="xd324-split-heading">
                <div><p class="xd324-eyebrow">{{ data_get($services, 'data.subtitle', 'Dịch vụ của chúng tôi') }}</p><h2>{!! data_get($services, 'data.title', 'Kiến tạo <em>Sự khác biệt</em> - Gói trọn mọi Ý tưởng') !!}</h2></div>
                <p>{{ data_get($services, 'data.description') }}</p>
            </div>
            <div class="xd324-card-track" data-drag-scroll>
                @forelse ($items($services) as $item)
                    <article class="xd324-service-card">
                        <img src="{{ $contentImage($item, 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title') }}">
                        <div><h3>{{ data_get($item, 'title') }}</h3><p>{{ $short(data_get($item, 'summary', data_get($item, 'description')), 95) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem thêm</a></div>
                    </article>
                @empty
                    <p class="xd324-empty">Đang cập nhật dịch vụ.</p>
                @endforelse
            </div>
            <div class="xd324-center"><a class="xd324-btn" href="#dich-vu">Xem thêm dịch vụ <i class="fa-solid fa-arrow-right"></i></a></div>
        </div>
    </section>

    <section id="du-an" class="xd324-section xd324-slider-block xd324-projects reveal" data-xd-block="{{ data_get($projects, 'id') }}">
        <div class="xd324-container">
            <div class="xd324-split-heading">
                <div><p class="xd324-eyebrow">{{ data_get($projects, 'data.subtitle', 'Dự án nổi bật') }}</p><h2>{!! data_get($projects, 'data.title', 'Dấu ấn Sáng tạo - <em>Tuyên ngôn Phong cách</em>') !!}</h2></div>
                <p>{{ data_get($projects, 'data.description') }}</p>
            </div>
            <div class="xd324-card-track xd324-card-track--wide" data-drag-scroll>
                @forelse ($items($projects) as $item)
                    <article class="xd324-project-card">
                        <img src="{{ $contentImage($item, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=85') }}" alt="{{ data_get($item, 'title') }}">
                        <a href="{{ data_get($item, 'url', '#') }}" aria-label="{{ data_get($item, 'title') }}"><i class="fa-solid fa-arrow-up-right"></i></a>
                        <h3>{{ data_get($item, 'title') }}</h3>
                    </article>
                @empty
                    <p class="xd324-empty">Đang cập nhật dự án.</p>
                @endforelse
            </div>
            <div class="xd324-center"><a class="xd324-btn" href="#du-an">Xem thêm Dự án <i class="fa-solid fa-arrow-right"></i></a></div>
        </div>
    </section>

    <section class="xd324-process reveal" data-xd-block="{{ data_get($process, 'id') }}">
        <div class="xd324-container">
            <div class="xd324-process__head">
                <div><p class="xd324-eyebrow">{{ data_get($process, 'data.subtitle', 'Quy trình thực hiện') }}</p><h2>{!! data_get($process, 'data.title', 'Từ Bản vẽ Sơ phác đến <em>Công trình Hoàn thiện</em>') !!}</h2></div>
                <p>{{ data_get($process, 'data.description') }}</p>
            </div>
            <div class="xd324-process__grid">
                @foreach ($items($process) as $index => $item)
                    <article>
                        <i class="{{ data_get($item, 'icon', 'fa-regular fa-lightbulb') }}"></i>
                        <h3>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}. {{ data_get($item, 'title') }}</h3>
                        <p>{{ data_get($item, 'summary') }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="tin-tuc" class="xd324-section xd324-news reveal" data-xd-block="{{ data_get($news, 'id') }}">
        <div class="xd324-container">
            <div class="xd324-split-heading">
                <div><p class="xd324-eyebrow">{{ data_get($news, 'data.subtitle', 'Tin mới nhất') }}</p><h2>{!! data_get($news, 'data.title', 'Góc nhìn & <em>Xu hướng</em>') !!}</h2></div>
                <p>{{ data_get($news, 'data.description') }}</p>
            </div>
            <div class="xd324-news-track" data-drag-scroll>
                @forelse ($items($news) as $item)
                    <article>
                        <a href="{{ data_get($item, 'url', '#') }}">
                            <img src="{{ $contentImage($item, 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item, 'title') }}">
                            <h3>{{ data_get($item, 'title') }}</h3>
                            <p>{{ $short(data_get($item, 'summary', data_get($item, 'description')), 120) }}</p>
                            <time>{{ data_get($item, 'published_at', '04/11/2025') }}</time>
                        </a>
                    </article>
                @empty
                    <p class="xd324-empty">Đang cập nhật tin tức.</p>
                @endforelse
            </div>
        </div>
    </section>
    <span id="lien-he"></span><span id="xu-huong"></span><span id="san-pham"></span>
</main>
@endsection

