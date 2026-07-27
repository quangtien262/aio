@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm'));
    $url = data_get($item, 'url', '#');
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec904/phone-front.webp'));
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round(($original - $price) * 100 / $original) : 0;
@endphp
<article class="ec94-card">
    @if($discount)<span class="ec94-discount">Giảm<br>{{ $discount }}%</span>@endif
    <a href="{{ $url }}"><img src="{{ $image }}" alt="{{ $title }}" loading="lazy"></a>
    <div><h3><a href="{{ $url }}">{{ $title }}</a></h3><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : 'Liên hệ' }}</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.').'đ' }}</del>@endif</div>
</article>
