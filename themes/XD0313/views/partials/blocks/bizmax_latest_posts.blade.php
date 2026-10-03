@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 4))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-posts xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container">
        <p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Blog gần đây' }}</p>
        <h2 class="rx13-title">{{ $data['title'] ?? 'Một số bài viết của chúng tôi' }}</h2>
        <div class="rx13-posts__grid">
            @foreach ($items as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                    $date = $item['date'] ?? $item['published_at'] ?? '06/08/2025';
                    $views = $item['views'] ?? null;
                @endphp
                <article class="rx13-post">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    @endif
                    <div class="rx13-post__body">
                        <h3><a href="{{ $item['url'] ?? $item['href'] ?? '#blog' }}">{{ $title }}</a></h3>
                        <div class="rx13-meta"><span>{{ $date }}</span>@if($views !== null)<span>{{ $views }}</span>@endif</div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
