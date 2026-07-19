@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect(data_get($block, 'data.content.items', [])))
        ->whenEmpty(fn () => collect($featuredDeals))
        ->values();
    $title = data_get($block, 'data.title') ?: $featuredTitle;
@endphp

<section class="th-featured-panel">
    <div class="th-featured-topbar">
        <div class="th-section-tabs">
            <span>{{ $title }}</span>
            @if (filled(data_get($block, 'data.subtitle')))<span>{{ data_get($block, 'data.subtitle') }}</span>@endif
            <span>@themeT('home.good_price_tab', 'Giá tốt')</span>
        </div>
    </div>
    <div class="th-card-grid">
        @foreach ($items as $item)
            @include('theme-th0001::partials.blocks.product-card', ['item' => $item])
        @endforeach
    </div>
</section>
