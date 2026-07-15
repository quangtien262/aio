@php
    $slides = collect(data_get($content, 'slides', []))->filter()->values();

    if ($slides->isEmpty()) {
        $slides = collect($block['dynamic_items'] ?? [])->filter()->values();
    }

    if ($slides->isEmpty()) {
        $slides = collect([[
            'title' => $title ?? 'Visa de dang, giac mo thanh hien thuc',
            'subtitle' => $subtitle ?? 'RouteX Visa',
            'description' => data_get($content, 'description', 'Dong hanh cung ban tren moi hanh trinh du hoc, du lich va lam viec quoc te.'),
            'button_label' => data_get($content, 'button_label', 'Dat lich tu van'),
            'button_url' => data_get($content, 'button_url', '#lien-he'),
            'image_url' => data_get($content, 'image_url'),
        ]]);
    }
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section rx13-hero-block">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container">
        <div class="rx13-hero">
            @foreach($slides as $index => $slide)
                @php
                    $slideTitle = data_get($slide, 'title', $title);
                    $slideSubtitle = data_get($slide, 'subtitle', $subtitle);
                    $slideDescription = data_get($slide, 'description', data_get($slide, 'excerpt', data_get($content, 'description')));
                    $slideImage = data_get($slide, 'image_url', data_get($slide, 'image'));
                    $slideButton = data_get($slide, 'button_label', data_get($content, 'button_label', 'Dat lich tu van'));
                    $slideUrl = data_get($slide, 'button_url', data_get($slide, 'url', data_get($content, 'button_url', '#lien-he')));
                @endphp
                <article class="rx13-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-rx13-hero-slide>
                    <div class="rx13-hero__content">
                        @if(filled($slideSubtitle))
                            <p class="rx13-eyebrow">{{ $slideSubtitle }}</p>
                        @endif
                        <h1>{{ $slideTitle }}</h1>
                        @if(filled($slideDescription))
                            <p class="rx13-hero__description">{{ $slideDescription }}</p>
                        @endif
                        <div class="rx13-hero__actions">
                            <a class="rx13-button rx13-button--outline" href="{{ $slideUrl }}">{{ $slideButton }} <span aria-hidden="true">&rarr;</span></a>
                            <a class="rx13-video-link" href="{{ data_get($content, 'video_url', '#') }}"><span aria-hidden="true">&#9654;</span> Xem video cua chung toi</a>
                        </div>
                    </div>
                    <div class="rx13-hero__visual">
                        @if(filled($slideImage))
                            <img src="{{ $slideImage }}" alt="{{ $slideTitle }}">
                        @else
                            <div class="rx13-hero__fallback" aria-hidden="true">RouteX</div>
                        @endif
                    </div>
                </article>
            @endforeach
            @if($slides->count() > 1)
                <div class="rx13-hero__dots" aria-label="Chuyen slide">
                    @foreach($slides as $index => $slide)
                        <button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-rx13-hero-dot="{{ $index }}" aria-label="Slide {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
