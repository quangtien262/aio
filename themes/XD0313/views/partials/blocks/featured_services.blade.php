@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 6))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-services xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container">
        <p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Visa nổi bật' }}</p>
        <h2 class="rx13-title">{{ $data['title'] ?? 'Các loại Visa phổ biến' }}</h2>
        <div class="rx13-services__grid">
            @foreach ($items as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                @endphp
                <article class="rx13-service-card">
                    @if (filled($image))
                        <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                    @endif
                    <div>
                        <h3>{{ $title }}</h3>
                        @if (filled($summary))
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 145) }}</p>
                        @endif
                        <a class="rx13-open" href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">↗</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
