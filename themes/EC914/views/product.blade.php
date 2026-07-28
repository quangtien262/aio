@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $image = data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url'));
    $price = data_get($product ?? null, 'price', data_get($productModel ?? null, 'price'));
    $original = data_get($product ?? null, 'original_price', data_get($productModel ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description'));
@endphp
@extends('theme-ec914::layout')
@section('title', $title)
@section('content')
<main><section class="ec14-content"><div class="ec14-container ec14-product-detail"><div><img src="{{ $image ?: '/theme-demo/ec914/product-bag-round.webp' }}" alt="{{ $title }}"></div><div><p>SẢN PHẨM THỦ CÔNG</p><h1>{{ $title }}</h1><p>@if($original && $original > $price)<del>{{ number_format((float) $original, 0, ',', '.') }}đ</del>@endif</p><strong>{{ number_format((float) $price, 0, ',', '.') }}đ</strong><p>Chất liệu tự nhiên · Hoàn thiện thủ công · Gói quà tinh tế</p><form action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">@csrf<input type="number" name="quantity" value="1" min="1"><button class="ec14-button">Thêm vào giỏ</button></form><div class="ec14-prose">{!! $body !!}</div></div></div></section></main>
@endsection
