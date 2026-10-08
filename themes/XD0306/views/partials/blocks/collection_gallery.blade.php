@php($items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->take((int) ($settings['limit'] ?? 6)))
<section id="{{ $anchor }}" class="xd6-section xd6-gallery-section xd-landing-block" data-landing-block-id="{{ $block['id'] ?? '' }}" data-block-type="collection_gallery">
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
                    <img src="{{ ($item['image'] ?? '') ?: '/theme-demo/xd-shared/digital-1.jpg' }}" alt="{{ $item['alt'] ?? $item['title'] ?? '' }}">
                    <span class="xd6-gallery__caption"><strong>{{ $item['title'] ?? '' }}</strong><i aria-hidden="true">↗</i></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
