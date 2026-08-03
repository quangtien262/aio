@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 3))
        ->values();
@endphp

<section id="{{ $anchor }}" class="fg18-section fg18-posts xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="fg18-container">
        <header class="fg18-center">
            <p class="fg18-kicker">{{ $data['subtitle'] ?? 'Tin tức mới' }}</p>
            <h2 class="fg18-title">{{ $data['title'] ?? 'Đọc tin tức mới nhất của chúng tôi' }}</h2>
        </header>
        <div class="fg18-posts__grid">
            @foreach ($items as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                    $date = $item['published_at'] ?? $item['date'] ?? '24/03/2022';
                    $views = $item['views'] ?? (320 + ($loop->index * 39));
                @endphp
                <article class="fg18-post">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    @endif
                    <div class="fg18-post__meta"><span>{{ $date }}</span> | Luot xem: {{ $views }}</div>
                    <h3>{{ $title }}</h3>
                    @if (filled($summary))
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 165) }}</p>
                    @endif
                    <a class="fg18-more" href="{{ $item['url'] ?? $item['href'] ?? '#tin-tuc' }}">+ Xem thêm</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
