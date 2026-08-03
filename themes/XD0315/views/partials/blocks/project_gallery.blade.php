@php
    $clubItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 5))
        ->values();
@endphp

<section id="{{ $anchor }}" class="af15-clubs xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="af15-title-row af15-title-row--dark">
        <h2>{{ $data['title'] ?? 'Câu lạc bộ' }}</h2>
        @if (filled($data['description'] ?? null))
            <p>{{ $data['description'] }}</p>
        @endif
    </div>
    <div class="af15-club-grid">
        @foreach ($clubItems as $item)
            @php
                $title = $item['title'] ?? $item['name'] ?? '';
                $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '';
            @endphp
            <a class="af15-club-card {{ $loop->iteration <= 2 ? 'is-wide' : '' }}" href="{{ $item['url'] ?? $item['href'] ?? '#cau-lac-bo' }}">
                @if (filled($image))
                    <img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}">
                @endif
                <strong>{{ $title }}</strong>
            </a>
        @endforeach
    </div>
</section>
