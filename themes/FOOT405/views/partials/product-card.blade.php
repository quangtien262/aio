@php
    $title = (string) data_get($item, 'title', 'Sản phẩm');
    $image = (string) data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec916/product-grocery.webp'));
    $url = (string) data_get($item, 'url', '#');
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="f405-product" data-f405-reveal><a class="f405-product__image" href="{{ $url }}">@if($discount > 0)<b>-{{ $discount }}%</b>@endif<img src="{{ $image }}" alt="{{ data_get($item, 'alt', $title) }}" loading="lazy"></a><div class="f405-product__body"><h3><a href="{{ $url }}">{{ $title }}</a></h3><div class="f405-stars" aria-label="5 sao">★★★★★</div><div class="f405-product__price">@if($price > 0)<strong>{{ number_format($price, 0, ',', '.') }}đ</strong>@else<strong>@themeT('FOOT405.contact_price', 'Liên hệ')</strong>@endif @if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif</div><div class="f405-product__buy"><span>@themeT('FOOT405.in_stock', 'Còn hàng')</span><a href="{{ $url }}" aria-label="Xem {{ $title }}"><i class="fa-solid fa-basket-shopping"></i></a></div></div></article>
