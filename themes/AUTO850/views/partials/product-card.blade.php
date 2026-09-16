@php
    $title = data_get($item, 'title', data_get($item, 'name', ''));
    $image = data_get($item, 'image', data_get($item, 'image_url', asset('themes/AUTO850/images/accessory-1.png')));
    $url = data_get($item, 'url', '#san-pham');
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
@endphp
<article class="a850-product-card">
    <a class="a850-product-image" href="{{ $url }}">@if($original > $price && $price > 0)<span>-{{ (int) round((1 - $price / $original) * 100) }}%</span>@endif<img src="{{ $image }}" alt="{{ $title }}" loading="lazy"><i class="fa-solid fa-cart-plus"></i></a>
    <div class="a850-product-copy"><h3><a href="{{ $url }}">{{ $title }}</a></h3><div class="a850-price"><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO850', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong>@if($original > $price && $price > 0)<del>{{ number_format($original, 0, ',', '.').'đ' }}</del>@endif</div></div>
</article>
