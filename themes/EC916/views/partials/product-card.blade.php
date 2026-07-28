@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm ưu đãi'));
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec916/product-grocery.webp'));
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="ec16-product" data-ec16-stagger>
    <a class="ec16-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ $title }}">@if($discount)<span>-{{ $discount }}%</span>@endif</a>
    <div><h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3><p><strong>{{ number_format($price, 0, ',', '.') }}đ</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif</p><small><i class="fa-regular fa-user"></i> {{ data_get($item, 'stock', random_int(8, 28)) }} đã mua</small></div>
</article>
