@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $price = data_get($product ?? null, 'price', data_get($productModel ?? null, 'price'));
    $original = data_get($product ?? null, 'original_price', data_get($productModel ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec916::layout')
@section('title', $title)
@section('content')
<main><section class="ec16-content"><div class="ec16-container ec16-product-detail"><div><img src="{{ $image ?: '/theme-demo/ec916/product-grocery.webp' }}" alt="{{ $title }}"></div><div><p>ƯU ĐÃI CHÍNH HÃNG</p><h1>{{ $title }}</h1><p>@if($original && $original > $price)<del>{{ number_format((float) $original, 0, ',', '.') }}đ</del>@endif</p><strong>{{ number_format((float) $price, 0, ',', '.') }}đ</strong><p>Giao hàng nhanh · Đổi trả dễ dàng · Thanh toán linh hoạt</p><form action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">@csrf<input type="number" name="quantity" value="1" min="1"><button class="ec16-button">Thêm vào giỏ</button></form><div class="ec16-prose">{!! $body !!}</div></div></div></section></main>
@endsection
