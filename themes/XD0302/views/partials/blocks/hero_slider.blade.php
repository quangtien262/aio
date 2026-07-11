@php
    $slides = collect($content['slides'] ?? [])->filter(fn ($item) => is_array($item) && filled($item['image'] ?? null))
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->values();
    $slides = $slides->isNotEmpty() ? $slides : collect([['title' => $data['title'] ?? '', 'summary' => $data['description'] ?? '', 'image' => 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=1920&q=85']]);
@endphp
<section id="{{ $anchor }}" class="xd2-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="hero_slider" data-xd2-hero data-autoplay="{{ $settings['autoplay_ms'] ?? 6000 }}">
    {!! $editButton !!}
    @foreach ($slides as $slide)
        <article class="xd2-hero__slide {{ $loop->first ? 'is-active' : '' }}"><img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? 'Banner' }}"><div class="xd2-container xd2-hero__copy"><div><p>{{ $slide['kicker'] ?? $data['subtitle'] ?? '' }}</p><h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1><p>{{ $slide['summary'] ?? $data['description'] ?? '' }}</p><a class="xd2-button" href="{{ $slide['link_url'] ?? '#du-an' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Xem dự án' }} <span>→</span></a></div></div></article>
    @endforeach
    <button class="xd2-hero__arrow is-prev" type="button" data-xd2-hero-prev aria-label="Banner trước">←</button><button class="xd2-hero__arrow is-next" type="button" data-xd2-hero-next aria-label="Banner tiếp theo">→</button>
    <div class="xd2-hero__dots">@foreach ($slides as $slide)<button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-xd2-hero-dot="{{ $loop->index }}" aria-label="Banner {{ $loop->iteration }}"></button>@endforeach</div>
</section>
