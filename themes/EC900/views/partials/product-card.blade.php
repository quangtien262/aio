@php
    $original = (float) data_get($item, 'original_price');
    $price = (float) data_get($item, 'price');
    $off = $original > $price && $original > 0 ? round((1 - $price / $original) * 100) : 0;
    $cardImage = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec900/air-purifier.webp'));
    $url = data_get($item, 'url', '#');
    $formatMoney = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
@endphp
<article class="ec9-product">
    <a class="ec9-product-image" href="{{ $url }}">
        @if($off)<b>-{{ $off }}%</b>@endif
        <img src="{{ $cardImage }}" alt="{{ data_get($item, 'title') }}" loading="lazy">
        <span class="ec9-product-tools"><i class="fa-regular fa-heart"></i><i class="fa-solid fa-magnifying-glass"></i><i class="fa-solid fa-cart-plus"></i></span>
    </a>
    <div class="ec9-product-body">
        <h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3>
        <div class="ec9-price"><strong>{{ $formatMoney($price) }}</strong>@if($original > $price)<del>{{ $formatMoney($original) }}</del>@endif</div>
        <p><span>{{ number_format((float) data_get($item, 'rating', 0), 1) }} ★</span> ({{ data_get($item, 'review_count', 0) }} Đánh giá)</p>
    </div>
</article>
