@php
    $classItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 6))
        ->values();
@endphp

<section id="{{ $anchor }}" class="af15-classes xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-split-title">
        <h2>{{ $data['title'] ?? 'Trung tam the duc Athletic !' }}</h2>
        @if (filled($data['description'] ?? null))
            <p>{{ $data['description'] }}</p>
        @endif
    </div>
    <div class="af15-class-grid">
        @foreach ($classItems as $item)
            @php
                $title = $item['title'] ?? $item['name'] ?? '';
                $summary = $item['summary'] ?? $item['description'] ?? $item['excerpt'] ?? '';
                $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
            @endphp
            <a class="af15-class-card {{ $loop->iteration === 3 || $loop->iteration === 4 ? 'is-wide' : '' }}" href="{{ $item['url'] ?? $item['href'] ?? '#lop-tap' }}">
                @if (filled($image))
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                @endif
                <span>
                    <strong>{{ $title }}</strong>
                    @if (filled($summary))
                        <small>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 170) }}</small>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
</section>
