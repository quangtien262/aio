@extends('theme-book920::layout')
@section('content')
@php
    $title = data_get($product ?? [], 'title', data_get($productModel ?? null, 'name', ''));
    $price = (float) data_get($product ?? [], 'price', 0);
    $original = (float) data_get($product ?? [], 'old_price', data_get($productModel ?? null, 'original_price', 0));
    $gallery = collect($productGallery ?? [])->filter(fn ($photo) => filled(data_get($photo, 'url')))->values();
    if ($gallery->isEmpty() && data_get($product ?? [], 'image')) $gallery->push(['url' => $product['image'], 'alt' => $title]);
@endphp
<main class="book20-inner">
<div class="book20-container">
    @include('theme-book920::partials.breadcrumb', ['current' => $title])
    @include('theme-book920::partials.feedback')
    <div class="book20-product-detail">
        <div class="book20-gallery" data-book20-gallery>
            <div class="book20-main-image">
                @if($gallery->isNotEmpty())<a href="{{ $gallery[0]['url'] }}" target="_blank" rel="noopener" data-book20-full aria-label="@themeT('inner.full_image', 'Mở ảnh lớn')"><img data-book20-main src="{{ $gallery[0]['url'] }}" alt="{{ $gallery[0]['alt'] ?? $title }}" width="600" height="740" fetchpriority="high"></a>@else<span>@themeT('inner.no_image', 'Hình ảnh đang cập nhật')</span>@endif
                @if($price > 0 && $original > $price)<span class="book20-sale">-{{ round((1 - $price / $original) * 100) }}%</span>@endif
            </div>
            @if($gallery->count() > 1)<div class="book20-thumbnails">@foreach($gallery as $photo)<button type="button" data-book20-thumb aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ $photo['alt'] ?? $title }}"><img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] ?? $title }}" width="70" height="90"></button>@endforeach</div>@endif
        </div>
        <section class="book20-product-info">
            <p class="book20-kicker">@themeT('inner.reading', 'Một cuốn sách, mở thêm thế giới')</p>
            @if(data_get($product ?? [], 'tag'))<span class="book20-pill">{{ $product['tag'] }}</span>@endif
            <h1>{{ $title }}</h1>
            @if(data_get($productModel ?? null, 'sku'))<p class="book20-muted">@themeT('inner.sku', 'Mã sản phẩm'): {{ $productModel->sku }}</p>@endif
            <div class="book20-price"><strong>@if($price > 0){{ number_format($price, 0, ',', '.') }}đ @else @themeT('inner.contact_price', 'Liên hệ báo giá') @endif</strong>@if($price > 0 && $original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del>@endif</div>
            @if(data_get($product ?? [], 'summary'))<div class="book20-prose book20-summary">{!! $product['summary'] !!}</div>@endif
            @if(!empty($productHighlights))<ul class="book20-highlights">@foreach($productHighlights as $highlight)<li>{{ $highlight }}</li>@endforeach</ul>@endif
            <form class="book20-purchase" method="post" action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}">@csrf
                <label for="book20-quantity">@themeT('inner.quantity', 'Số lượng')</label>
                <div class="book20-purchase-row"><div class="book20-quantity" data-book20-quantity><button type="button" data-book20-step="-1" aria-label="@themeT('inner.decrease', 'Giảm số lượng')">&minus;</button><input id="book20-quantity" name="quantity" type="number" value="1" min="1" max="99" required inputmode="numeric"><button type="button" data-book20-step="1" aria-label="@themeT('inner.increase', 'Tăng số lượng')">+</button></div><button class="book20-button" type="submit"><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>@themeT('inner.add_cart', 'Thêm vào giỏ')</button></div>
            </form>
            <a class="book20-consult" href="{{ route('site.contact') }}"><i class="fa-regular fa-comments" aria-hidden="true"></i>@themeT('inner.ask_book', 'Cần tìm sách? Liên hệ cùng chúng tôi') <span>&rarr;</span></a>
            <p class="book20-muted">@themeT('inner.delivery_note', 'Liên hệ để xác nhận thông tin ấn bản, giao nhận và chính sách đổi trả.')</p>
        </section>
    </div>
    <section class="book20-description"><div><p class="book20-kicker">@themeT('inner.discover', 'Khám phá nội dung')</p><h2>@themeT('inner.detail', 'Giới thiệu sách')</h2><div class="book20-prose">{!! data_get($productModel ?? null, 'detail_content') ?: data_get($product ?? [], 'summary', '') !!}</div>@if(!empty($usageTerms))<h3>@themeT('inner.notes', 'Thông tin cần biết')</h3><ul>@foreach($usageTerms as $term)<li>{{ $term }}</li>@endforeach</ul>@endif</div><aside class="book20-note"><i class="fa-solid fa-book-open" aria-hidden="true"></i><h2>@themeT('inner.find_title', 'Tìm cuốn sách dành cho bạn')</h2><p>@themeT('inner.find_text', 'Khám phá thêm những chủ đề và đầu sách phù hợp với sở thích đọc của bạn.')</p><a class="book20-button secondary" href="{{ route('site.catalog.search') }}">@themeT('inner.explore', 'Khám phá tủ sách') &rarr;</a></aside></section>
    @if(!empty($relatedProducts))<section class="book20-related"><h2>@themeT('inner.related', 'Có thể bạn muốn đọc')</h2><div class="book20-product-grid">@foreach(array_slice($relatedProducts, 0, 4) as $item)@include('theme-book920::partials.product-card', ['item' => $item])@endforeach</div></section>@endif
</div>
</main>
@endsection
