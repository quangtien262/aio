@php
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $price > 0 ? (int) round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec909/headphone-black.png'));
    $title = data_get($item, 'title', data_get($item, 'name', 'Sản phẩm âm thanh'));
@endphp
<article class="ec99-product">
    <a class="ec99-product-img" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ $title }}" loading="lazy">@if($discount)<em>- {{ $discount }}%</em>@endif<span><i class="fa-regular fa-eye"></i></span></a>
    <div class="ec99-swatch-line"><i></i><i></i><i></i></div>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3>
    <div class="ec99-price"><strong>{{ number_format($price, 0, ',', '.') }}₫</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}₫</del>@endif</div>
    <p>Màu sắc:</p><div class="ec99-colors"><i style="--c:#55272a"></i><i style="--c:#d8cec0"></i><i style="--c:#202020"></i></div>
    <footer><button aria-label="So sánh"><i class="fa-solid fa-shuffle"></i></button><button aria-label="Yêu thích"><i class="fa-solid fa-heart"></i></button><a href="{{ data_get($item, 'url', '#') }}">Xem chi tiết</a></footer>
</article>
