@php
    $slides = collect($content['slides'] ?? [])
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
@endphp

<section id="{{ $anchor }}" class="af15-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-hero__viewport" data-af15-hero>
        @foreach ($slides as $slide)
            <article class="af15-hero__slide {{ $loop->first ? 'is-active' : '' }}">
                <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? 'Hero' }}">
                <div class="af15-hero__shade"></div>
                <div class="af15-hero__cut"></div>
                <div class="af15-hero__content">
                    <p>{{ $slide['kicker'] ?? $data['subtitle'] ?? '' }}</p>
                    <h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1>
                    <a href="{{ $slide['link_url'] ?? '#lop-tap' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Dang ky tap thu' }} →</a>
                </div>
            </article>
        @endforeach
        <button class="af15-hero__nav prev" type="button" data-af15-hero-prev aria-label="Slide truoc">‹</button>
        <button class="af15-hero__nav next" type="button" data-af15-hero-next aria-label="Slide sau">›</button>
        <div class="af15-hero__dots">
            @foreach ($slides as $slide)
                <button class="{{ $loop->first ? 'is-active' : '' }}" type="button" data-af15-hero-dot="{{ $loop->index }}" aria-label="Slide {{ $loop->iteration }}"></button>
            @endforeach
        </div>
    </div>
</section>
