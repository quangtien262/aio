@php($slides = collect($content['slides'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->values())
<section id="{{ $anchor }}" class="xd4-hero" data-xd4-hero data-autoplay="{{ (int) ($settings['autoplay_ms'] ?? 6000) }}">
    @forelse($slides as $index => $slide)
        <article class="xd4-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-xd4-slide>
            <img src="{{ $slide['image'] ?? 'https://images.unsplash.com/photo-1494412651409-8963ce7935a7?auto=format&fit=crop&w=1920&q=85' }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? '' }}"><div class="xd4-hero__veil"></div>
            <div class="xd4-container xd4-hero__content"><div class="xd4-hero__copy"><p class="xd4-eyebrow">{{ $slide['kicker'] ?? $data['subtitle'] ?? '' }}</p><h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1><p>{{ $slide['summary'] ?? $slide['description'] ?? $data['description'] ?? '' }}</p><div class="xd4-actions"><a class="xd4-button xd4-button--outline" href="#lien-he">Tư vấn miễn phí</a><a class="xd4-button" href="{{ $slide['link_url'] ?? '#dich-vu' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Xem lộ trình' }} →</a></div></div></div>
        </article>
    @empty
        <article class="xd4-hero__slide is-active" data-xd4-slide><img src="https://images.unsplash.com/photo-1494412651409-8963ce7935a7?auto=format&fit=crop&w=1920&q=85" alt=""><div class="xd4-hero__veil"></div><div class="xd4-container xd4-hero__content"><div class="xd4-hero__copy"><p class="xd4-eyebrow">{{ $data['subtitle'] ?? '' }}</p><h1>{{ $data['title'] ?? '' }}</h1><p>{{ $data['description'] ?? '' }}</p></div></div></article>
    @endforelse
    @if($slides->count() > 1)<button class="xd4-hero__arrow xd4-hero__arrow--prev" type="button" data-xd4-prev>‹</button><button class="xd4-hero__arrow xd4-hero__arrow--next" type="button" data-xd4-next>›</button>@endif
</section>
