@php($items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->take((int) ($settings['limit'] ?? 6)))
<section id="{{ $anchor }}" class="xd6-section xd6-gallery-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="collection_gallery">
    <div class="xd6-container">
        <div class="xd6-gallery-section__head">
            <div>
                @if (!empty($data['subtitle']))
                    <p class="xd6-eyebrow">{{ $data['subtitle'] }}</p>
                @endif
                <h2 class="xd6-section-title">{{ $data['title'] ?? '' }}</h2>
            </div>
            @if (!empty($data['description']))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="xd6-gallery">
            @foreach ($items as $item)
                <a class="xd6-gallery__card" href="{{ $item['url'] ?? '#' }}">
                    <img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=85' }}" alt="{{ $item['alt'] ?? $item['title'] ?? '' }}">
                    <span>{{ $item['title'] ?? '' }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<style>
    .xd6-gallery-section { background: #f6f7f8; }
    .xd6-gallery-section__head { display: flex; align-items: end; justify-content: space-between; gap: 36px; margin-bottom: 32px; }
    .xd6-gallery-section__head p { max-width: 510px; color: var(--muted); line-height: 1.7; margin: 0; }
    .xd6-gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
    .xd6-gallery__card { position: relative; display: block; min-height: 260px; overflow: hidden; color: #fff; background: var(--ink); }
    .xd6-gallery__card:first-child { grid-column: span 2; min-height: 380px; }
    .xd6-gallery__card img { width: 100%; height: 100%; position: absolute; inset: 0; object-fit: cover; transition: transform .3s ease; }
    .xd6-gallery__card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(transparent 40%, rgba(11, 13, 18, .85)); }
    .xd6-gallery__card span { position: absolute; z-index: 1; left: 22px; right: 22px; bottom: 20px; font-weight: 700; font-size: 19px; }
    .xd6-gallery__card:hover img { transform: scale(1.05); }
    @media (max-width: 760px) { .xd6-gallery-section__head { display: block; } .xd6-gallery-section__head p { margin-top: 14px; } .xd6-gallery { grid-template-columns: 1fr; } .xd6-gallery__card:first-child { grid-column: auto; min-height: 300px; } }
</style>
