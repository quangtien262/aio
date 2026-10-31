@php
    // The quick editor writes localized slides here. An explicitly empty list means no slides.
    $heroContent = (array) data_get($hero, 'data.content', []);
    $slides = collect(array_key_exists('slides', $heroContent)
        ? $heroContent['slides']
        : ($hero['dynamic_items'] ?? []))->filter()->values();
@endphp

@if($slides->isNotEmpty())
<section id="{{ data_get($hero, 'anchor_id', 'top') }}" class="n502-hero xd-landing-block" data-landing-block-id="{{ data_get($hero, 'id') }}" data-block-type="hero_slider" data-n502-slider data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6000) }}">
    @foreach($slides as $index => $slide)
        <article class="n502-hero-slide {{ $index === 0 ? 'is-active' : '' }}" data-n502-slide>
            <img src="{{ $image($slide, '/theme-demo/ec915/room-living-room.webp') }}" alt="{{ data_get($slide, 'alt', data_get($slide, 'title', data_get($hero, 'data.title'))) }}">
            <div class="n502-container">
                <div class="n502-hero-copy">
                    @if(filled($kicker = data_get($slide, 'kicker') ?: data_get($hero, 'data.subtitle')))
                        <span class="n502-hero-kicker">{{ $kicker }}</span>
                    @endif
                    <h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1>
                    <p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p>
                    <a href="{{ data_get($slide, 'link_url', '#phong-khach') }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label')) }}</a>
                </div>
            </div>
        </article>
    @endforeach
</section>
@endif
