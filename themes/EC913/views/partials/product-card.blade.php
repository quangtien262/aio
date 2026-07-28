@php
    $price = (int) data_get($item, 'price', 0);
    $original = (int) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
    $title = data_get($item, 'title', data_get($item, 'name'));
@endphp

<article class="ec13-product {{ ($compact ?? false) ? 'is-compact' : '' }}">
    <div class="ec13-product-top">
        @if($discount)<span class="ec13-discount">-{{ $discount }}%</span>@endif
        <button type="button" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i></button>
    </div>
    <a class="ec13-product-image" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec913/phone-graphite.webp')) }}" alt="{{ $title }}"></a>
    <div class="ec13-product-meta"><span>Trả góp 0%</span><span>Chính hãng</span></div>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3>
    <div class="ec13-price"><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : 'Liên hệ' }}</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif</div>
    <p>{{ data_get($item, 'summary', 'Tặng gói bảo hành mở rộng và miễn phí giao hàng.') }}</p>
    <div class="ec13-rating"><span>★★★★★</span><small>({{ 12 + (($loop->iteration ?? 1) * 7) }})</small></div>
</article>
