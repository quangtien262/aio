@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $price = data_get($product ?? null, 'price', data_get($productModel ?? null, 'price'));
    $original = data_get($product ?? null, 'original_price', data_get($productModel ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec902::layout')
@section('title', $title)
@section('content')
<main><section class="ec92-content"><div class="ec92-container ec92-product-detail">
    <div class="ec92-product-detail-image"><img src="{{ $image ?: '/theme-demo/ec902/phone-silver.webp' }}" alt="{{ $title }}"></div>
    <div><p class="ec92-kicker">NOVA SELECTED</p><h1>{{ $title }}</h1><div class="ec92-product-detail-price">@if($original && $original > $price)<del>{{ number_format((float) $original, 0, ',', '.') }}₫</del>@endif<strong>{{ number_format((float) $price, 0, ',', '.') }}₫</strong></div><p>Chính hãng · Miễn phí vận chuyển · Hỗ trợ đổi trả</p><form action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">@csrf<input type="number" name="quantity" value="1" min="1"><button class="ec92-button">Thêm vào giỏ hàng</button></form><div class="ec92-prose">{!! $body !!}</div></div>
</div></section></main>
@endsection
