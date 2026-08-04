@php
    $title = (string) data_get($item, 'title', 'Sản phẩm');
    $image = (string) data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec903/food-dessert.webp'));
    $url = (string) data_get($item, 'url', '#');
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="f404-product" data-f404-reveal>
    <a class="f404-product__image" href="{{ $url }}">
        @if($discount > 0)<b>-{{ $discount }}%</b>@endif
        <img src="{{ $image }}" alt="{{ data_get($item, 'alt', $title) }}" loading="lazy">
    </a>
    <div class="f404-product__body">
        <div class="f404-stars" aria-label="5 sao">★★★★★</div>
        <h3><a href="{{ $url }}">{{ $title }}</a></h3>
        <span class="f404-stock">@themeT('FOOT404.in_stock', 'Còn hàng')</span>
        <div class="f404-product__buy">
            <div>@if($price > 0)<strong>{{ number_format($price, 0, ',', '.') }}đ</strong>@else<strong>@themeT('FOOT404.contact_price', 'Liên hệ')</strong>@endif @if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif</div>
            <a href="{{ $url }}" aria-label="Xem {{ $title }}"><i class="fa-solid fa-basket-shopping"></i></a>
        </div>
    </div>
</article>
