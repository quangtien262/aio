@php
    $mosaicItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 5))
        ->values();

    $mosaicTitle = trim((string) ($data['title'] ?? ''));
    $mosaicSubtitle = trim((string) ($data['subtitle'] ?? ''));
@endphp

@if ($mosaicItems->isNotEmpty())
<section id="{{ $anchor }}" class="xd-section xd-content-mosaic xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="xd-container">
        <div class="xd8-discovery-head">
            @if ($mosaicSubtitle !== '')<p class="xd8-kicker">{{ $mosaicSubtitle }}</p>@endif
            @if ($mosaicTitle !== '')<h2>{{ $mosaicTitle }}</h2>@endif
            @if (filled($data['description'] ?? null))<p class="xd8-discovery-intro">{{ $data['description'] }}</p>@endif
        </div>

        <div class="xd-mosaic-grid">
            @foreach ($mosaicItems as $item)
                @php
                    $itemTitle = (string) ($item['title'] ?? $item['name'] ?? '');
                    $itemSummary = (string) ($item['summary'] ?? $item['excerpt'] ?? $item['description'] ?? $item['tag'] ?? '');
                    $itemUrl = (string) ($item['url'] ?? $item['href'] ?? '#');
                    $itemImage = (string) ($item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '');
                @endphp
                <a class="xd8-discovery-card" href="{{ $itemUrl }}">
                    <div class="xd8-discovery-media">
                        @if (filled($itemImage))
                            <img src="{{ $itemImage }}" alt="{{ $item['alt'] ?? $itemTitle }}" loading="lazy">
                        @else
                            <span class="xd8-discovery-placeholder" aria-hidden="true">↗</span>
                        @endif
                    </div>
                    <div class="xd8-discovery-body">
                        <h3>{{ $itemTitle }}</h3>
                        @if ($itemSummary !== '')<p>{{ \Illuminate\Support\Str::limit(strip_tags($itemSummary), 115) }}</p>@endif
                        <span class="xd8-discovery-link">{{ $data['button_label'] ?? 'Khám phá thêm' }} <span aria-hidden="true">↗</span></span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
