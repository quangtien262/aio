@php
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
    $iteration = isset($loop) ? $loop->iteration : 1;
@endphp
<article class="ec12-product">
    @if($discount)<span class="ec12-discount">Giảm {{ $discount }}%</span>@endif
    <a class="ec12-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec912/phone-graphite.webp')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a>
    <div class="ec12-product-badges"><span>Trả góp 0%</span><span>BH 24 tháng</span></div>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h3>
    <div class="ec12-price">
        @if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif
        <strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : 'Liên hệ' }}</strong>
    </div>
    <p>Giảm <b>250.000đ</b> khi mua kèm gói bảo hành VIP 12 tháng 1 Đổi 1.</p>
    @if($hot ?? false)<div class="ec12-sold"><span style="width:{{ min(94, 42 + ($iteration * 8)) }}%"></span><b>🔥 Đã bán {{ 120 + $iteration * 29 }}</b></div>@endif
</article>
