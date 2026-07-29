@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm nội thất'));
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec917/product-sofa-ivory.webp'));
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
    $sold = (int) data_get($item, 'sold', data_get($item, 'stock', 17));
@endphp
<article class="ec17-product" data-ec17-stagger>
    <a class="ec17-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ $title }}">@if(data_get($item, 'badge'))<span>{{ data_get($item, 'badge') }}</span>@endif</a>
    <small class="ec17-brand">EGA FURNITURE</small>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3>
    <div class="ec17-stars">★★★★★</div>
    <p class="ec17-price"><strong>{{ number_format($price, 0, ',', '.') }}đ</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del><em>-{{ $discount }}%</em>@endif</p>
    <div class="ec17-swatches"><i style="--swatch:#d9d2c7"></i><i style="--swatch:#b9b7b1"></i><i style="--swatch:#795548"></i></div>
    <p class="ec17-sold">{{ $sold > 45 ? '🔥 Sắp cháy hàng' : 'Đã bán '.$sold.' sản phẩm' }}</p>
    <div class="ec17-progress"><span style="width:{{ min(95, max(18, $sold)) }}%"></span></div>
</article>
