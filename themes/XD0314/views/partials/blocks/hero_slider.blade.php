@php
    $slides = collect($content['slides'] ?? [])
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
@endphp

<section id="{{ $anchor }}" class="bb14-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-hero__viewport" data-bb14-hero>
        @foreach ($slides as $slide)
            <article class="bb14-hero__slide {{ $loop->first ? 'is-active' : '' }}">
                <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? 'Hero' }}">
                <div class="bb14-hero__overlay"></div>
                <div class="bb14-container bb14-hero__content">
                    <h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1>
                    <p>{{ $slide['summary'] ?? $data['description'] ?? '' }}</p>
                    <div class="bb14-hero__actions">
                        <a class="bb14-button bb14-button--ghost" href="{{ $slide['quote_url'] ?? '#footer' }}">Nhan bao gia</a>
                        <a class="bb14-button" href="{{ $slide['link_url'] ?? '#dich-vu' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Xem them' }} <span>►</span></a>
                    </div>
                </div>
            </article>
        @endforeach
        <button class="bb14-hero__nav prev" type="button" data-bb14-hero-prev aria-label="Slide truoc">‹</button>
        <button class="bb14-hero__nav next" type="button" data-bb14-hero-next aria-label="Slide sau">›</button>
    </div>
</section>

