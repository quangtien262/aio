@php($items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->take($settings['limit'] ?? 10))
@if ($items->isNotEmpty())
<section id="{{ $anchor }}" class="xd5-section xd9-news-slider">
    <div class="xd5-container">
        <header class="xd5-team-head">
            <p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
            <h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2>
        </header>
        <div class="xd5-posts" data-xd9-news-track tabindex="0" aria-label="Danh sách tin tức, cuộn ngang để xem thêm">
            @foreach($items as $item)
                <article class="xd5-post">
                    <img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=900&q=85' }}" alt="">
                    <div>
                        <small>{{ now()->format('d/m/Y') }}</small>
                        <h3>{{ $item['title'] ?? '' }}</h3>
                        <p>{{ str($item['summary'] ?? '')->limit(160) }}</p>
                        <a class="xd5-btn" href="{{ $item['url'] ?? '#' }}">{{ $data['button_label'] ?? 'Đọc thêm' }}</a>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($items->count() > 1)
            <div class="xd9-news-controls"><button type="button" data-xd9-news-prev aria-label="Tin trước">←</button><button type="button" data-xd9-news-next aria-label="Tin tiếp theo">→</button></div>
        @endif
    </div>
</section>
@endif
