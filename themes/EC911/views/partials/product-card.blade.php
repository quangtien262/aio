@php
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="ec11-product-card">
    @if($discount)<span class="ec11-discount">- {{ $discount }}%</span>@endif
    <a class="ec11-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec911/camera-pro.png')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h3>
    <strong>{{ number_format($price, 0, ',', '.') }}₫</strong>
    @if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}₫</del>@endif
</article>
