@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $price = data_get($product ?? null, 'price', data_get($productModel ?? null, 'price'));
    $original = data_get($product ?? null, 'original_price', data_get($productModel ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec915::layout')
@section('title', $title)
@section('content')
<main class="ec15-product-page">
    <section class="ec15-content">
        <div class="ec15-container">
            <nav class="ec15-product-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><a href="{{ route('site.catalog.search') }}">Sản phẩm</a><span>/</span><span>{{ $title }}</span></nav>
            <div class="ec15-product-detail">
                <figure class="ec15-product-visual"><img src="{{ $image ?: '/theme-demo/ec915/product-sofa-ivory.webp' }}" alt="{{ $title }}"></figure>
                <div class="ec15-product-info">
                    <span class="ec15-product-eyebrow">BỘ SƯU TẬP NỘI THẤT</span>
                    <h1>{{ $title }}</h1>
                    @if(data_get($productModel, 'sku'))<p class="ec15-product-sku">Mã sản phẩm: <strong>{{ data_get($productModel, 'sku') }}</strong></p>@endif
                    <div class="ec15-product-price">
                        <strong>{{ number_format((float) $price, 0, ',', '.') }}<small>đ</small></strong>
                        @if($original && $original > $price)
                            <del>{{ number_format((float) $original, 0, ',', '.') }}đ</del>
                            <span class="ec15-product-discount">−{{ round((1 - (float) $price / (float) $original) * 100) }}%</span>
                        @endif
                    </div>
                    @if(data_get($productModel, 'short_description'))<div class="ec15-product-summary">{!! data_get($productModel, 'short_description') !!}</div>@endif
                    <form class="ec15-product-purchase" action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">
                        @csrf
                        <label for="ec15-product-quantity">Số lượng</label>
                        <div class="ec15-product-buyrow">
                            <div class="ec15-product-quantity"><button type="button" data-ec15-quantity="-1" aria-label="Giảm số lượng">−</button><input id="ec15-product-quantity" type="number" name="quantity" value="1" min="1" step="1" required><button type="button" data-ec15-quantity="1" aria-label="Tăng số lượng">+</button></div>
                            <button class="ec15-product-add" type="submit"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 7h12l2 14H4L6 7Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>Thêm vào giỏ hàng</button>
                        </div>
                    </form>
                    <button class="ec15-product-advice" type="button" data-ec915-question-open aria-haspopup="dialog" aria-controls="ec915-question-dialog"><span>Cần tư vấn về sản phẩm này?</span><span aria-hidden="true">↗</span></button>
                    <p class="ec15-product-note">Liên hệ để xác nhận kích thước, chất liệu, thời gian giao hàng và chính sách bảo hành.</p>
                    @if($body)<section class="ec15-product-description"><h2>Thông tin sản phẩm</h2><div class="ec15-prose">{!! $body !!}</div></section>@endif
                </div>
            </div>
        </div>
    </section>
</main>
@include('theme-ec915::partials.product-detail-styles')
<script>
document.querySelectorAll('[data-ec15-quantity]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById('ec15-product-quantity');
    input.value = Math.max(1, (parseInt(input.value, 10) || 1) + Number(button.dataset.ec15Quantity));
    input.dispatchEvent(new Event('change', {bubbles:true}));
}));
</script>

@include('themes.common.product-recommendations', ['showRelatedRecommendations' => true])
@endsection
