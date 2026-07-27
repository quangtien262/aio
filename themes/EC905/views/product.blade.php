@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $price = data_get($product ?? null, 'price', data_get($productModel ?? null, 'price'));
    $original = data_get($product ?? null, 'original_price', data_get($productModel ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec905::layout')
@section('title', $title)
@section('content')
<main><section class="ec95-content"><div class="ec95-container ec95-product-detail"><div><img src="{{ $image ?: '/theme-demo/ec905/product-01.webp' }}" alt="{{ $title }}"></div><div><p>EGO HOME CHÍNH HÃNG</p><h1>{{ $title }}</h1><p>@if($original && $original > $price)<del>{{ number_format((float) $original, 0, ',', '.') }}đ</del>@endif</p><strong>{{ number_format((float) $price, 0, ',', '.') }}đ</strong><p>Giao hàng nhanh · Đổi trả minh bạch · Hỗ trợ lắp đặt tận tâm</p><form action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">@csrf<input type="number" name="quantity" value="1" min="1"><button class="ec95-button">Thêm vào giỏ</button></form><div class="ec95-prose">{!! $body !!}</div></div></div></section></main>
@endsection
