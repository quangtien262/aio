@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect(data_get($block, 'data.content.items', [])))
        ->whenEmpty(fn () => collect($featuredCategories))
        ->values();
    $tones = ['#ef2b2d', '#ff8a00', '#7c3aed', '#0891b2', '#16a34a', '#db2777'];
@endphp

<section class="th-brand-strip" aria-label="{{ data_get($block, 'data.title', 'Danh mục nổi bật') }}">
    @foreach ($items as $item)
        @php
            $name = $item['title'] ?? $item['name'] ?? $item['label'] ?? 'Danh mục';
            $url = $item['url'] ?? $item['link_url'] ?? '#';
            $tone = $item['tone'] ?? $tones[$loop->index % count($tones)];
        @endphp
        <a href="{{ $url }}" target="{{ $item['target'] ?? '_self' }}" class="th-brand">
            <div class="th-brand-badge" style="background: {{ $tone }}">{{ $name }}</div>
        </a>
    @endforeach
</section>
