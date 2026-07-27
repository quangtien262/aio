@php
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $price > 0 ? (int) round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec906/home-care.png'));
@endphp
<article class="ec96-product-card">
    <a class="ec96-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image }}" alt="{{ data_get($item, 'title', data_get($item, 'name', 'Sản phẩm')) }}" loading="lazy">@if(data_get($item, 'badge'))<span>{{ data_get($item, 'badge') }}</span>@endif</a>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name', 'Sản phẩm')) }}</a></h3>
    <div class="ec96-price"><strong>{{ number_format($price, 0, ',', '.') }}₫</strong><button type="button" aria-label="Thêm vào giỏ"><i class="fa-solid fa-basket-shopping"></i></button></div>
    @if($original > $price)<p><del>{{ number_format($original, 0, ',', '.') }}₫</del><em>-{{ $discount }}%</em></p>@endif
</article>
