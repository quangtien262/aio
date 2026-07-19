@php
    $title = $item['title'] ?? $item['name'] ?? 'Sản phẩm';
    $image = $item['image'] ?? $item['image_url'] ?? $item['thumbnail'] ?? 'https://picsum.photos/seed/th0001-product/640/480';
    $url = $item['url'] ?? $item['link_url'] ?? '#';
    $discount = (int) ($item['discount'] ?? $item['discount_percent'] ?? 0);
@endphp

<article class="th-deal-card">
    <div class="th-deal-image-wrap">
        <a href="{{ $url }}"><img src="{{ $image }}" alt="{{ $item['alt'] ?? $title }}"></a>
        <span class="th-deal-chip">{{ $item['tag'] ?? $item['kicker'] ?? 'Sản phẩm' }}</span>
    </div>
    <div class="th-deal-body">
        <h3 class="th-deal-title"><a href="{{ $url }}">{{ $title }}</a></h3>
        @if (array_key_exists('price', $item) || array_key_exists('old_price', $item))
            <div class="th-pricing">
                <span class="th-price">{{ $formatCurrency($item['price'] ?? null) }}</span>
                @if ($discount > 0)<span class="th-discount">{{ $formatDiscount($discount) }}</span>@endif
            </div>
            <div class="th-old-price-row">
                @if (!empty($item['old_price']))<span class="th-old-price">{{ $formatCurrency($item['old_price']) }}</span>@endif
                @if (isset($item['meta']))<span class="th-stat">{{ str_replace(':count', (string) $item['meta'], $t('home.stock', 'Tồn kho :count')) }}</span>@endif
            </div>
        @elseif (filled($item['summary'] ?? null))
            <p class="th-hero-summary" style="color:var(--th-muted);margin:8px 0 0">{{ $item['summary'] }}</p>
        @endif
    </div>
</article>
