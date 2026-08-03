@php
    $newsItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 3))
        ->values();
@endphp

<section id="{{ $anchor }}" class="af15-news af15-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-container">
        <h2 class="af15-center-title">{{ $data['title'] ?? 'Tin tức sự kiện' }}</h2>
        <div class="af15-news-grid">
            @foreach ($newsItems as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                @endphp
                <article class="af15-news-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    @endif
                    <h3>{{ $title }}</h3>
                    @if (filled($summary))
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 150) }}</p>
                    @endif
                    <a href="{{ $item['url'] ?? $item['href'] ?? '#tin-tuc' }}">Xem thêm ↪</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
