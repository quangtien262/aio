@php
    $original = (float) data_get($item, 'original_price');
    $price = (float) data_get($item, 'price');
    $off = $original > $price && $original > 0 ? round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec902/phone-blue.webp'));
    $url = data_get($item, 'url', '#');
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
@endphp
<article class="ec92-product">
    <a class="ec92-product-image" href="{{ $url }}">@if($off)<b>Giảm {{ $off }}%</b>@endif<img src="{{ $image }}" alt="{{ data_get($item, 'title') }}" loading="lazy"><span>Mới</span></a>
    <div class="ec92-product-body"><h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3><p><strong>{{ $money($price) }}</strong>@if($original > $price)<del>{{ $money($original) }}</del>@endif</p><small>{{ data_get($item, 'summary', 'Bảo hành chính hãng 12 tháng') }}</small><footer><span><i class="fa-regular fa-heart"></i> Thích</span><span><i class="fa-regular fa-circle"></i> So sánh</span></footer></div>
</article>
