@php($title = data_get($item, 'title', 'Sản phẩm thời trang'))
@php($price = (int) data_get($item, 'price', 0))
@php($old = (int) data_get($item, 'original_price', 0))
@php($image = data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/shop604/product-women-knit.png')))
<article class="s606-card"><a class="s606-card-media" href="{{ data_get($item, 'url', '#') }}">@if($old > $price && $price > 0)<span>-{{ max(1, (int) round((1 - $price / $old) * 100)) }}%</span>@endif<img src="{{ $image }}" alt="{{ $title }}" loading="lazy"></a><div><small>{{ data_get($item, 'summary', 'COLLECTION') }}</small><h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3><p>☆ ☆ ☆ ☆ ☆</p>@if($price > 0)<strong>{{ number_format($price, 0, ',', '.') }}₫</strong>@endif @if($old > $price)<del>{{ number_format($old, 0, ',', '.') }}₫</del>@endif</div></article>
