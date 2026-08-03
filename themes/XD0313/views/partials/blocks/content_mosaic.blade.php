@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? null))
        ->take((int) ($settings['limit'] ?? 6))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-featured xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container">
        <p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Visa nổi bật' }}</p>
        <h2 class="rx13-title">{{ $data['title'] ?? 'Danh mục Visa nổi bật' }}</h2>
        <div class="rx13-featured-track" data-rx13-row>
            @foreach ($items as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                @endphp
                <a class="rx13-featured-card" href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    <strong>{{ $title }}</strong>
                </a>
            @endforeach
        </div>
    </div>
</section>
