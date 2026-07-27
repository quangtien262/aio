@php
    $original = (float) data_get($item, 'original_price');
    $price = (float) data_get($item, 'price');
    $off = $original > $price && $original > 0 ? round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec903/deal-seafood.webp'));
    $url = data_get($item, 'url', '#');
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
@endphp
<article class="ec93-deal-card">
    <a class="ec93-deal-image" href="{{ $url }}"><img src="{{ $image }}" alt="{{ data_get($item, 'title') }}" loading="lazy">@if(data_get($item, 'featured', true))<b>HOT</b>@endif<span><i class="fa-solid fa-ticket"></i> E-Voucher</span></a>
    <div><h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3><hr><p><strong>{{ $money($price) }}</strong>@if($off)<em>-{{ $off }}%</em>@endif</p>@if($original > $price)<del>{{ $money($original) }}</del>@endif<small><i class="fa-regular fa-user"></i> {{ data_get($item, 'sold_count', number_format(120 + crc32((string) data_get($item, 'title')) % 8000)) }}</small></div>
</article>
