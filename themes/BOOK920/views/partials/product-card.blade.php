@php
    $title = data_get($item, 'title', data_get($item, 'name', 'Sách tuyển chọn'));
    $image = data_get($item, 'image', data_get($item, 'image_url', ''));
    $price = (float) data_get($item, 'price', 0);
    $original = (float) data_get($item, 'old_price', data_get($item, 'original_price', 0));
    $discount = $original > $price && $original > 0 ? (int) round((1 - $price / $original) * 100) : 0;
@endphp
<article class="book20-product" data-book20-stagger>
    <a class="book20-product-image" href="{{ data_get($item, 'url', '#') }}">
        @if($image)<img src="{{ $image }}" alt="{{ $title }}" loading="lazy">@else<i class="fa-solid fa-book-open" aria-hidden="true"></i>@endif

    </a>
    <h3><a href="{{ data_get($item, 'url', '#') }}">{{ $title }}</a></h3>
    <p><strong>@if($price > 0){{ number_format($price, 0, ',', '.') }}đ @else @themeT('inner.contact_price', 'Liên hệ báo giá') @endif</strong>@if($original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif @if($discount)<em>- {{ $discount }}%</em>@endif</p>
    <a class="book20-cart" href="{{ data_get($item, 'url', '#') }}"><i class="fa-solid fa-basket-shopping"></i> @themeT('inner.view_book', 'Xem sách')</a>
</article>
