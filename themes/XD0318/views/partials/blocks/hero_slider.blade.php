@php
    $slides = collect($content['slides'] ?? [])
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
@endphp

<section id="{{ $anchor }}" class="fg18-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-hero__viewport" data-fg18-hero data-autoplay="{{ (int) ($settings['autoplay_ms'] ?? 6000) }}">
        @foreach ($slides as $slide)
            <article class="fg18-hero__slide {{ $loop->first ? 'is-active' : '' }}" data-fg18-slide>
                <img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? 'Fast Gear' }}">
                <div class="fg18-hero__content">
                    <h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1>
                    @if (filled($slide['summary'] ?? $data['description'] ?? null))
                        <p>{{ $slide['summary'] ?? $data['description'] }}</p>
                    @endif
                    <div class="fg18-hero__actions">
                        <a class="fg18-button fg18-button--ghost" href="{{ $slide['quote_url'] ?? '#lien-he' }}">Nhan bao gia</a>
                        <a class="fg18-button" href="{{ $slide['link_url'] ?? '#gioi-thieu' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Xem them' }}</a>
                    </div>
                </div>
            </article>
        @endforeach
        <button class="fg18-hero__nav prev" type="button" data-fg18-hero-prev aria-label="Slide truoc">&lsaquo;</button>
        <button class="fg18-hero__nav next" type="button" data-fg18-hero-next aria-label="Slide sau">&rsaquo;</button>
    </div>
</section>
