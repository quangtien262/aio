@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm nội thất'));
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec915/product-sofa-ivory.webp'));
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="ec15-product" data-ec15-stagger>
    <a class="ec15-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ $title }}" loading="lazy">@if($discount)<span>- {{ $discount }}%</span>@endif<div><i class="fa-regular fa-heart"></i><i class="fa-solid fa-arrow-right-arrow-left"></i></div></a>
    <div class="ec15-product-meta"><h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3><p>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif<strong>{{ number_format($price, 0, ',', '.') }}đ</strong></p></div>
</article>
