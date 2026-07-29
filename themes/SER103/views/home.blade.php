@extends('theme-ser103::layout')

@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = collect(data_get($landingEditorOptions ?? [], 'locales', []))->all();
    $findBlock = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block): array {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty()
            ? $dynamic->all()
            : collect(data_get($block, 'data.content.items', data_get($block, 'data.content.slides', [])))->filter()->values()->all();
    };
    $hero = $findBlock('hero_slider');
    $heroSlides = $items($hero);
    $heroSlides = $heroSlides ?: [[
        'kicker' => 'Bøhu Wedding',
        'title' => 'Lập kế hoạch cho đám cưới của bạn',
        'summary' => 'Chúng tôi biến ngày trọng đại thành một câu chuyện đầy cảm xúc và dấu ấn riêng.',
        'button_label' => 'Đặt lịch hẹn',
        'link_url' => '#lien-he',
        'image' => '/theme-previews/SER103/hero-wedding.webp',
    ]];
    $editButton = fn (array $block) => $canEditLanding && filled($block['id'] ?? null);
@endphp

@section('title', data_get($landingPage ?? [], 'title', 'Bøhu Wedding'))

@section('content')
<main class="ser103-main">
    <section class="ser103-hero" id="{{ $hero['anchor_id'] ?? 'trang-chu' }}" data-landing-block-id="{{ $hero['id'] ?? '' }}" data-block-type="hero_slider" data-ser103-hero data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6000) }}">
        <div class="ser103-hero__copy">
            <p data-ser103-kicker>{{ data_get($heroSlides[0], 'kicker', data_get($hero, 'data.subtitle')) }}</p>
            <h1 data-ser103-title>{{ data_get($heroSlides[0], 'title', data_get($hero, 'data.title')) }}</h1>
            <div class="ser103-hero__summary" data-ser103-summary>{{ data_get($heroSlides[0], 'summary', data_get($hero, 'data.description')) }}</div>
            <div class="ser103-hero__actions">
                <a class="ser103-button is-light" href="#dich-vu">Dịch vụ của chúng tôi</a>
                <a class="ser103-button" data-ser103-link href="{{ data_get($heroSlides[0], 'link_url', '#lien-he') }}">{{ data_get($heroSlides[0], 'button_label', 'Đặt lịch hẹn') }} <i class="fa-solid fa-arrow-right-long"></i></a>
            </div>
        </div>
        <div class="ser103-hero__visual">
            @foreach($heroSlides as $index => $slide)
                <img class="{{ $index === 0 ? 'is-active' : '' }}" data-ser103-slide src="{{ data_get($slide, 'image', '/theme-previews/SER103/hero-wedding.webp') }}" alt="{{ data_get($slide, 'title', 'Bøhu Wedding') }}">
            @endforeach
            <div class="ser103-hero__dots">@foreach($heroSlides as $index => $slide)<button class="{{ $index === 0 ? 'is-active' : '' }}" type="button" data-ser103-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>@endforeach</div>
        </div>
        @if($editButton($hero))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $hero['id'] }}">Sửa khối</button>@endif
    </section>

    @foreach($blocks->reject(fn ($entry) => ($entry['block_type'] ?? '') === 'hero_slider') as $entry)
        @php
            $type = $entry['block_type'] ?? '';
            $entryItems = $items($entry);
            $title = data_get($entry, 'data.title', '');
            $subtitle = data_get($entry, 'data.subtitle', '');
            $description = data_get($entry, 'data.description', '');
        @endphp

        @if($type === 'ser103_about')
            <section class="ser103-section ser103-about" data-ser103-reveal id="{{ $entry['anchor_id'] ?? 'gioi-thieu' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                <div class="ser103-about__image is-main"><img src="{{ data_get($entryItems[0], 'image', '/theme-previews/SER103/about-couple.webp') }}" alt="{{ data_get($entryItems[0], 'title', $title) }}"></div>
                <div class="ser103-about__content">
                    <span>{{ $subtitle }}</span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $description }}</p>
                    <div class="ser103-flower">❦</div>
                </div>
                <div class="ser103-about__image is-top"><img src="{{ data_get($entryItems[1], 'image', '/theme-previews/SER103/about-bride.webp') }}" alt="Cô dâu"></div>
                <div class="ser103-about__image is-bottom"><img src="{{ data_get($entryItems[2], 'image', '/theme-previews/SER103/about-rings.webp') }}" alt="Nhẫn cưới"></div>
                <div class="ser103-about__image is-small"><img src="{{ data_get($entryItems[3], 'image', '/theme-previews/SER103/about-fashion.webp') }}" alt="Váy cưới"></div>
                @if($editButton($entry))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @elseif($type === 'business_service_grid')
            <section class="ser103-section ser103-services" data-ser103-reveal id="{{ $entry['anchor_id'] ?? 'dich-vu' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                <div class="ser103-container">
                    <div class="ser103-heading"><span>{{ $subtitle }}</span><h2>{{ $title }}</h2><p>{{ $description }}</p></div>
                    <div class="ser103-service-grid">
                        @foreach($entryItems as $index => $item)
                            <a class="ser103-service-card {{ $index < 2 ? 'is-featured' : '' }}" href="{{ data_get($item, 'url', route('site.services.index')) }}">
                                <img src="{{ data_get($item, 'image', '/theme-previews/SER103/service-planning.webp') }}" alt="{{ data_get($item, 'title') }}">
                                <div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p><span>Khám phá <i class="fa-solid fa-arrow-right-long"></i></span></div>
                            </a>
                        @endforeach
                    </div>
                </div>
                @if($editButton($entry))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @elseif($type === 'latest_posts')
            <section class="ser103-section ser103-news" data-ser103-reveal id="{{ $entry['anchor_id'] ?? 'tin-tuc' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                <div class="ser103-container">
                    <div class="ser103-heading"><span>{{ $subtitle }}</span><h2>{{ $title }}</h2><p>{{ $description }}</p></div>
                    <div class="ser103-news-grid">
                        @foreach(array_slice($entryItems, 0, 3) as $item)
                            <article>
                                <a href="{{ data_get($item, 'url', route('site.blog.index')) }}"><img src="{{ data_get($item, 'image', '/theme-previews/SER103/news-minimal.webp') }}" alt="{{ data_get($item, 'title') }}"></a>
                                <div class="ser103-news__meta"><span><i class="fa-regular fa-calendar"></i> {{ data_get($item, 'date', '18/11/2025') }}</span><span><i class="fa-regular fa-eye"></i> {{ data_get($item, 'views', 68) }}</span></div>
                                <h3><a href="{{ data_get($item, 'url', route('site.blog.index')) }}">{{ data_get($item, 'title') }}</a></h3>
                                <p>{{ data_get($item, 'summary') }}</p>
                                <a class="ser103-more" href="{{ data_get($item, 'url', route('site.blog.index')) }}">Xem thêm <i class="fa-solid fa-arrow-right-long"></i></a>
                            </article>
                        @endforeach
                    </div>
                </div>
                @if($editButton($entry))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @elseif($type === 'landing_contact')
            <section class="ser103-contact" data-ser103-reveal id="{{ $entry['anchor_id'] ?? 'lien-he' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                <div class="ser103-contact__image"><img src="{{ data_get($entry, 'media.image', '/theme-previews/SER103/contact-couple.webp') }}" alt="{{ $title }}"></div>
                <div class="ser103-contact__form">
                    <span>{{ $subtitle }}</span><h2>{{ $title }}</h2><p>{{ $description }}</p>
                    <form action="{{ route('site.contact.submit') }}" method="post">@csrf
                        <input type="hidden" name="source" value="contact">
                        <label><b>*</b><input name="name" placeholder="Họ và tên" required></label>
                        <label><b>*</b><input type="email" name="email" placeholder="Email" required></label>
                        <label><b>*</b><input name="phone" placeholder="Số điện thoại" required></label>
                        <label><input name="address" placeholder="Địa chỉ"></label>
                        <label class="is-wide"><b>*</b><textarea name="message" minlength="10" rows="5" placeholder="Nội dung" required></textarea></label>
                        <button type="submit">{{ data_get($entry, 'data.button_label', 'Gửi đi') }} <i class="fa-solid fa-arrow-right-long"></i></button>
                    </form>
                </div>
                @if($editButton($entry))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @elseif($type === 'collection_gallery')
            <section class="ser103-gallery" data-ser103-reveal id="{{ $entry['anchor_id'] ?? 'thu-vien' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                @foreach(array_slice($entryItems, 0, 5) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title', 'Bộ sưu tập cưới') }}"></a>@endforeach
                @if($editButton($entry))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @endif
    @endforeach
</main>
@endsection
