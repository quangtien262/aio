@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect(data_get($block, 'data.content.items', [])))
        ->values();
@endphp

<section class="th-category-section">
    <div class="th-category-header">
        <div class="th-category-title">
            <span class="th-category-title-badge">✦</span>
            <span>{{ data_get($block, 'data.title', 'Khám phá thêm') }}</span>
        </div>
        @if (filled(data_get($block, 'data.subtitle')))
            <div class="th-category-tabs"><span>{{ data_get($block, 'data.subtitle') }}</span></div>
        @endif
        @if (filled(data_get($block, 'data.description')))
            <div class="th-category-filters"><span>{{ data_get($block, 'data.description') }}</span></div>
        @endif
    </div>
    <div class="th-category-grid">
        @foreach ($items as $item)
            @include('theme-th0001::partials.blocks.product-card', ['item' => $item])
        @endforeach
    </div>
</section>
