@php
    $serviceItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->values();
@endphp

<section id="{{ $anchor }}" class="bb14-services bb14-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-container">
        <div class="bb14-section-title">
            <h2>{{ $data['title'] ?? 'Dịch vụ của chúng tôi' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="bb14-service-carousel" data-bb14-row>
            @foreach ($serviceItems as $item)
                @php
                    $title = $item['title'] ?? $item['name'] ?? '';
                    $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
                @endphp
                <article class="bb14-service-card">
                    <a href="{{ $item['url'] ?? $item['href'] ?? '#dich-vu' }}">
                        @if (filled($image))
                            <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                        @endif
                        <span>
                            <strong>{{ $title }}</strong>
                            @if (filled($summary))
                                <small>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 120) }}</small>
                            @endif
                        </span>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
