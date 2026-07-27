@php
    $original = (float) data_get($item, 'original_price');
    $price = (float) data_get($item, 'price');
    $off = $original > $price && $original > 0 ? round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec901/classic-silver.webp'));
    $url = data_get($item, 'url', '#');
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
@endphp
<article class="ec91-product">
    <a class="ec91-product-image" href="{{ $url }}">
        @if($off)<b>{{ $off }}%</b>@endif
        <img src="{{ $image }}" alt="{{ data_get($item, 'title') }}" loading="lazy">
        <span><i class="fa-regular fa-heart"></i><i class="fa-regular fa-eye"></i><i class="fa-solid fa-bag-shopping"></i></span>
    </a>
    <div class="ec91-product-body">
        <h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3>
        <p>@if($original > $price)<del>{{ $money($original) }}</del>@endif<strong>{{ $money($price) }}</strong></p>
    </div>
</article>
