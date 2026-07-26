@php($slides = collect($content['slides'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])))
<section id="{{ $anchor }}" class="xd5-hero" data-xd5-hero data-autoplay="{{ $settings['autoplay_ms'] ?? 6000 }}">
    @forelse($slides as $i => $slide)
        <article class="xd5-hero__slide {{ $i === 0 ? 'is-active' : '' }}" data-xd5-slide>
            <img src="{{ $slide['image'] ?? 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1920&q=85' }}" alt="">
            <span class="xd5-veil"></span>
            <div class="xd5-container xd5-hero-copy">
                <div>
                    <p class="xd5-kicker">{{ $slide['kicker'] ?? $data['subtitle'] ?? '' }}</p>
                    <h1>{{ $slide['title'] ?? $data['title'] ?? '' }}</h1>
                    <p>{{ $slide['summary'] ?? $data['description'] ?? '' }}</p>
                    <a class="xd5-btn" href="{{ $slide['link_url'] ?? '#lien-he' }}">{{ $slide['button_label'] ?? $data['button_label'] ?? 'Nhận báo giá' }}</a>
                </div>
            </div>
        </article>
    @empty
        <article class="xd5-hero__slide is-active" data-xd5-slide>
            <img src="https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1920&q=85" alt="">
            <span class="xd5-veil"></span>
            <div class="xd5-container xd5-hero-copy">
                <div>
                    <p class="xd5-kicker">{{ $data['subtitle'] ?? '' }}</p>
                    <h1>{{ $data['title'] ?? '' }}</h1>
                    <p>{{ $data['description'] ?? '' }}</p>
                </div>
            </div>
        </article>
    @endforelse
</section>
