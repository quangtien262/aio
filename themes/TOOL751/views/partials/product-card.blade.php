@php
    $title = data_get($item, 'title', data_get($item, 'name', ''));
    $image = data_get($item, 'image', data_get($item, 'image_url', asset('themes/TOOL751/images/product-drill.png')));
    $url = data_get($item, 'url', '#san-pham');
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'original_price', 0);
@endphp
<article class="t751-product-card">
    <a class="t751-product-image" href="{{ $url }}">@if($original > $price && $price > 0)<span>-{{ (int) round((1 - $price / $original) * 100) }}%</span>@endif<img src="{{ $image }}" alt="{{ $title }}" loading="lazy"><i class="fa-solid fa-cart-plus"></i></a>
    <div class="t751-product-copy"><h3><a href="{{ $url }}">{{ $title }}</a></h3><div class="t751-price"><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong>@if($original > $price && $price > 0)<del>{{ number_format($original, 0, ',', '.').'đ' }}</del>@endif</div></div>
</article>
