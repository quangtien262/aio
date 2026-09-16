@php
    $title = data_get($item, 'title', data_get($item, 'name', ''));
    $image = data_get($item, 'image', data_get($item, 'image_url', asset('themes/AUTO852/images/product-1.png')));
    $url = data_get($item, 'url', '#san-pham');
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
@endphp
<article class="a852-product-card">
    <a class="a852-product-image" href="{{ $url }}">@if($original > $price && $price > 0)<span>-{{ (int) round((1 - $price / $original) * 100) }}%</span>@endif<img src="{{ $image }}" alt="{{ $title }}" loading="lazy"></a>
    <div class="a852-product-copy"><small>0.0 <i class="fa-solid fa-star"></i> (0 đánh giá)</small><h3><a href="{{ $url }}">{{ $title }}</a></h3><div class="a852-price"><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong>@if($original > $price && $price > 0)<del>{{ number_format($original, 0, ',', '.').'đ' }}</del>@endif</div><a class="a852-product-cta" href="{{ $url }}"><i class="fa-solid fa-cart-shopping"></i> {{ $price > 0 ? 'Thêm vào giỏ' : 'Xem chi tiết' }}</a></div>
</article>
