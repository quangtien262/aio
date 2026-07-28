@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm thủ công'));
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec914/product-bag-round.webp'));
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - ($price / $original)) * 100) : 0;
@endphp
<article class="ec14-product">
    <a class="ec14-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ $title }}" loading="lazy">@if($discount)<span class="ec14-discount">-{{ $discount }}%</span>@endif</a>
    <div class="ec14-product-meta"><h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3><div class="ec14-price">@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif<strong>{{ number_format($price, 0, ',', '.') }}đ</strong></div><button type="button" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i></button></div>
</article>
