@php
    $mosaicItems = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? $item['name'] ?? null))
        ->take((int) ($settings['limit'] ?? 5))
        ->values();

    $mosaicTitle = trim((string) ($data['title'] ?? ''));
    $mosaicSubtitle = trim((string) ($data['subtitle'] ?? ''));
    $titleParts = preg_split('/\s+/', $mosaicTitle, 3) ?: [];
    $firstTitlePart = implode(' ', array_slice($titleParts, 0, 2));
    $accentTitlePart = $titleParts[2] ?? '';
@endphp

<section id="{{ $anchor }}" class="xd-section xd-content-mosaic xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="xd-container">
        <div class="xd-mosaic-head">
            @if ($mosaicTitle !== '')
                <h2>
                    <span>{{ $firstTitlePart !== '' ? $firstTitlePart : $mosaicTitle }}</span>
                    @if ($accentTitlePart !== '')
                        <em>{{ $accentTitlePart }}</em>
                    @endif
                </h2>
            @endif
            @if ($mosaicSubtitle !== '')
                <p>{{ $mosaicSubtitle }}</p>
            @endif
        </div>

        <div class="xd-mosaic-grid">
            @foreach ($mosaicItems as $item)
                @php
                    $itemTitle = (string) ($item['title'] ?? $item['name'] ?? '');
                    $itemSummary = (string) ($item['summary'] ?? $item['excerpt'] ?? $item['description'] ?? $item['tag'] ?? '');
                    $itemUrl = (string) ($item['url'] ?? $item['href'] ?? '#');
                    $itemImage = (string) ($item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? '');
                    $renderImageCard = filled($itemImage) && $loop->iteration % 2 === 1;
                @endphp

                @if ($renderImageCard)
                    <a class="xd-mosaic-card xd-mosaic-image" href="{{ $itemUrl }}">
                        <img src="{{ $itemImage }}" alt="{{ $item['alt'] ?? $itemTitle }}">
                    </a>
                @else
                    <a class="xd-mosaic-card xd-mosaic-copy" href="{{ $itemUrl }}">
                        <strong>{{ $itemTitle }}</strong>
                        @if ($itemSummary !== '')
                            <span>{{ \Illuminate\Support\Str::limit(strip_tags($itemSummary), 120) }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</section>
