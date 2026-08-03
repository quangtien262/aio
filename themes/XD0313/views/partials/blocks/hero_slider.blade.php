@php
    $slides = collect($content['slides'] ?? [])
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-hero__viewport" data-rx13-hero data-autoplay="{{ (int) ($settings['autoplay_ms'] ?? 6500) }}">
        @foreach ($slides as $slide)
            <article class="rx13-hero__slide {{ $loop->first ? 'is-active' : '' }}" data-rx13-slide>
                <span class="rx13-hero__orb"></span>
                <img class="rx13-hero__image" src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? 'RouteX' }}">
                <div class="rx13-hero__content">
                    <h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1>
                    @if (filled($slide['summary'] ?? $data['description'] ?? null))
                        <p>{{ $slide['summary'] ?? $data['description'] }}</p>
                    @endif
                    <div class="rx13-hero__actions">
                        <a class="rx13-button" href="{{ $slide['link_url'] ?? '#gioi-thieu' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Đọc thêm' }} <span>→</span></a>
                        <a class="rx13-hero__watch" href="{{ $slide['video_url'] ?? '#video' }}"><span class="rx13-play">▶</span> Xem video của chúng tôi</a>
                    </div>
                </div>
            </article>
        @endforeach
        <div class="rx13-hero__dots">
            @foreach ($slides as $slide)
                <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-rx13-hero-dot="{{ $loop->index }}" aria-label="Slide {{ $loop->iteration }}"></button>
            @endforeach
        </div>
    </div>
</section>
