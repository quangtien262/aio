@php
    $original = (float) data_get($item, 'original_price');
    $price = (float) data_get($item, 'price');
    $off = $original > $price && $original > 0 ? round((1 - $price / $original) * 100) : 0;
    $image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec910/classic-silver.webp'));
    $url = data_get($item, 'url', '#');
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'₫' : 'Liên hệ';
@endphp
<article class="ec10-product">
    <a class="ec10-product-image" href="{{ $url }}">
        @if($off)<b>Giảm<br>{{ $off }}%</b>@endif
        <img src="{{ $image }}" alt="{{ data_get($item, 'title') }}" loading="lazy">
        <span><i class="fa-solid fa-fire"></i> Bán chạy</span>
    </a>
    <div class="ec10-product-body">
        <h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3>
        <p><strong>{{ $money($price) }}</strong>@if($original > $price)<del>{{ $money($original) }}</del>@endif</p>
        <small>{{ data_get($item, 'summary', 'Miễn phí thay pin trọn đời cho tất cả khách hàng') }}</small>
    </div>
</article>
