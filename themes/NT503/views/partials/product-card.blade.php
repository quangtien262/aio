@php
    $price = (float) data_get($item, 'price');
    $old = (float) data_get($item, 'original_price');
    $img = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/nt503/mattress.png'));
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
    $discount = $old > $price && $old > 0 ? (int) round((1 - $price / $old) * 100) : 0;
@endphp
<article class="n503-product">
    <a class="n503-product-image" href="{{ data_get($item, 'url', '#') }}">
        @if($discount > 0)<b>- {{ $discount }}%</b>@endif
        <img src="{{ $img }}" alt="{{ data_get($item, 'title') }}">
    </a>
    <div>
        <h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3>
        <p><span>{{ data_get($item, 'rating', '0.0') }} ★</span> ({{ data_get($item, 'review_count', 0) }} Đánh giá)</p>
        <strong>{{ $money($price) }}</strong>@if($old > $price)<del>{{ $money($old) }}</del>@endif
        @if(!empty($sale))<small>🔥 {{ data_get($item, 'sold', 'Vừa mở bán') }}</small><i class="n503-progress"><i></i></i>@endif
        <a class="n503-add" href="{{ data_get($item, 'url', '#') }}"><i class="fa-solid fa-cart-shopping"></i> {{ data_get($item, 'stock', 1) > 0 ? 'Thêm vào giỏ' : 'Hết hàng' }}</a>
    </div>
</article>
